<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\BadRequestException;
use App\Exceptions\CentreMismatchException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\Procurement;
use App\Models\QueueEntry;
use App\Validators\ProcurementValidator;

class ProcurementService
{
    private Procurement $procurements;
    private ProcurementValidator $validator;
    private SettingService $settings;
    private AuditService $audit;
    private ScopeService $scope;

    private const PHOTO_SOURCE_TYPE = 'procurement_photo';

    public function __construct()
    {
        $this->procurements = new Procurement();
        $this->validator = new ProcurementValidator();
        $this->settings = new SettingService();
        $this->audit = new AuditService();
        $this->scope = new ScopeService();
    }

    public function start(array $actor, int $entryId): array
    {
        $entry = $this->requireQueueEntry($entryId);
        $this->assertCentreScope($actor, (int) $entry['centre_id']);

        if (!in_array((string) $entry['status'], [QueueEntry::STATUS_CALLED, QueueEntry::STATUS_IN_PROGRESS], true)) {
            throw new ConflictException('ENTRY_NOT_ELIGIBLE', 'Only called or in-progress queue entries can be started');
        }

        $bookingId = (int) $entry['booking_id'];
        $booking = Database::selectOne(
            "SELECT id, user_id, centre_id, status FROM bookings WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$bookingId]
        );
        if ($booking === null) {
            throw new NotFoundException('PROC_NOT_FOUND', 'Booking not found');
        }

        $existing = Database::selectOne(
            "SELECT COUNT(*) AS total FROM procurements WHERE booking_id = ? AND deleted_at IS NULL",
            [$bookingId]
        );
        if ((int) $existing['total'] > 0) {
            throw new ConflictException('ENTRY_NOT_ELIGIBLE', 'Procurements already exist for this booking');
        }

        $crops = Database::select(
            "SELECT * FROM booking_crops WHERE booking_id = ? AND status = 'PENDING' ORDER BY id ASC",
            [$bookingId]
        );

        if (empty($crops)) {
            throw new ConflictException('ENTRY_NOT_ELIGIBLE', 'Booking has no pending crop lines to procure');
        }

        $now = date('Y-m-d H:i:s');
        $created = [];

        Database::beginTransaction();
        try {
            foreach ($crops as $crop) {
                $id = Database::insert('procurements', [
                    'procurement_number' => $this->nextProcurementNumber(),
                    'booking_id' => $bookingId,
                    'booking_crop_id' => (int) $crop['id'],
                    'centre_id' => (int) $entry['centre_id'],
                    'user_id' => (int) $booking['user_id'],
                    'crop_name' => (string) $crop['crop_name'],
                    'variety' => $crop['variety'] !== null && $crop['variety'] !== '' ? (string) $crop['variety'] : null,
                    'quantity_kg' => 0.0,
                    'accepted_weight' => 0.0,
                    'damaged_qty' => 0.0,
                    'status' => Procurement::STATUS_PENDING,
                ]);

                $created[] = $this->procurements->byId($id);
            }

            Database::update(
                'queue_entries',
                [
                    'status' => QueueEntry::STATUS_IN_PROGRESS,
                    'started_at' => $now,
                    'started_by' => (int) $actor['id'],
                ],
                'id = ? AND status = ?',
                [(int) $entry['id'], (string) $entry['status']]
            );

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => (string) ($actor['name'] ?? ''),
                'user_role' => (string) ($actor['role'] ?? ''),
                'action' => 'PROC_START',
                'module' => 'PROCUREMENT',
                'entity_type' => 'queue_entry',
                'entity_id' => (int) $entry['id'],
                'new_value' => [
                    'booking_id' => $bookingId,
                    'centre_id' => (int) $entry['centre_id'],
                    'procurement_count' => count($created),
                ],
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return [
            'booking_id' => $bookingId,
            'queue_entry' => (int) $entry['id'],
            'procurement_count' => count($created),
            'procurements' => array_map(fn($p) => $this->present($p), $created),
        ];
    }

    public function capture(array $actor, int $id, array $data): array
    {
        $data = $this->validator->capture($data);
        $proc = $this->requireProcurement($id);
        $this->assertCentreScope($actor, (int) $proc['centre_id']);

        if ((string) $proc['status'] !== Procurement::STATUS_PENDING) {
            throw new ConflictException('STATUS_IMMUTABLE', 'Procurement can only be edited while PENDING');
        }

        $weightPrecision = max(0, (int) $this->settings->getInt('procurement.weight_precision', 2));
        $acceptedWeight = round((float) $data['accepted_weight'], $weightPrecision);
        $damagedQty = 0.0;
        if (isset($data['damaged_qty']) && $data['damaged_qty'] !== '') {
            $damagedQty = round((float) $data['damaged_qty'], $weightPrecision);
        }

        $bookedQty = $this->bookedQtyFor((int) $proc['booking_crop_id']);
        if ($bookedQty !== null && $damagedQty > $bookedQty) {
            throw new BadRequestException('WEIGHT_INVALID', 'Damaged quantity cannot exceed booked quantity');
        }

        $maxPhotos = max(1, (int) $this->settings->getInt('procurement.max_photos', 3));
        $photoIds = $data['photo_ids'] ?? [];
        if (count($photoIds) > $maxPhotos) {
            throw new BadRequestException('WEIGHT_INVALID', 'Maximum ' . $maxPhotos . ' photos allowed');
        }

        $this->replacePhotos((int) $proc['id'], $photoIds);

        $updateData = [
            'quantity_kg' => $acceptedWeight,
            'accepted_weight' => $acceptedWeight,
            'damaged_qty' => $damagedQty,
            'quality_grade' => isset($data['grade']) && $data['grade'] !== '' ? (string) $data['grade'] : null,
            'moisture_percent' => isset($data['moisture_pct']) && $data['moisture_pct'] !== '' ? (float) $data['moisture_pct'] : null,
            'notes' => isset($data['quality_notes']) && $data['quality_notes'] !== '' ? (string) $data['quality_notes'] : null,
            'operator_note' => isset($data['operator_note']) && $data['operator_note'] !== '' ? (string) $data['operator_note'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Database::update('procurements', $updateData, 'id = ?', [$id]);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PROC_CAPTURE',
            'module' => 'PROCUREMENT',
            'entity_type' => 'procurement',
            'entity_id' => $id,
            'new_value' => [
                'accepted_weight' => $acceptedWeight,
                'damaged_qty' => $damagedQty,
                'grade' => $updateData['quality_grade'],
                'moisture_pct' => $updateData['moisture_percent'],
                'photo_count' => count($photoIds),
            ],
        ]);

        return $this->present($this->procurements->byId($id));
    }

    public function submit(array $actor, int $id): array
    {
        $proc = $this->requireProcurement($id);
        $this->assertCentreScope($actor, (int) $proc['centre_id']);

        if ((string) $proc['status'] !== Procurement::STATUS_PENDING) {
            throw new ConflictException('STATUS_IMMUTABLE', 'Only pending procurements can be submitted');
        }

        $approvalRequired = $this->approvalRequiredForCentre((int) $proc['centre_id']);

        $now = date('Y-m-d H:i:s');
        $nextStatus = $approvalRequired ? Procurement::STATUS_PENDING_APPROVAL : Procurement::STATUS_VERIFIED;
        $updateData = [
            'status' => $nextStatus,
            'verified_at' => $nextStatus === Procurement::STATUS_VERIFIED ? $now : null,
            'verified_by' => $nextStatus === Procurement::STATUS_VERIFIED ? (int) $actor['id'] : null,
            'updated_at' => $now,
        ];

        Database::update('procurements', $updateData, 'id = ?', [$id]);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PROC_SUBMIT',
            'module' => 'PROCUREMENT',
            'entity_type' => 'procurement',
            'entity_id' => $id,
            'new_value' => [
                'status' => $nextStatus,
                'approval_policy' => $approvalRequired ? 'manual' : 'auto',
            ],
        ]);

        if ($nextStatus === Procurement::STATUS_VERIFIED && $approvalRequired === false) {
            $this->fireVerificationPaymentHook($id, (int) $actor['id']);
        }

        return $this->present($this->procurements->byId($id));
    }

    public function reject(array $actor, int $id, array $data): array
    {
        $reason = is_string($data['reason'] ?? null) && trim((string) $data['reason']) !== '' ? (string) $data['reason'] : '';
        if ($reason === '') {
            throw new BadRequestException('REASON_REQUIRED', 'A rejection reason is required');
        }

        $data = $this->validator->reject($data);
        $proc = $this->requireProcurement($id);
        $this->assertCentreScope($actor, (int) $proc['centre_id']);

        if (!in_array((string) $proc['status'], [Procurement::STATUS_PENDING, Procurement::STATUS_PENDING_APPROVAL], true)) {
            throw new ConflictException('STATUS_IMMUTABLE', 'Only pending or pending-approval procurements can be rejected');
        }

        $reason = (string) $data['reason'];

        Database::update(
            'procurements',
            [
                'status' => Procurement::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$id]
        );

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PROC_REJECT',
            'module' => 'PROCUREMENT',
            'entity_type' => 'procurement',
            'entity_id' => $id,
            'new_value' => ['status' => Procurement::STATUS_REJECTED],
            'reason' => $reason,
        ]);

        return $this->present($this->procurements->byId($id));
    }

    public function operatorList(array $actor, array $filters): array
    {
        $filters = $this->validator->listFilters($filters);
        $centreIds = $this->scope->centreIdsFor($actor);

        if (empty($centreIds)) {
            return ['count' => 0, 'items' => []];
        }

        $placeholders = implode(',', array_fill(0, count($centreIds), '?'));
        $where = "p.centre_id IN ({$placeholders}) AND p.deleted_at IS NULL";
        $params = $centreIds;

        if (!empty($filters['status'])) {
            $where .= ' AND p.status = ?';
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['date'])) {
            $where .= ' AND DATE(p.created_at) = ?';
            $params[] = (string) $filters['date'];
        }

        if (!empty($filters['q'])) {
            $q = '%' . (string) $filters['q'] . '%';
            $where .= ' AND (p.procurement_number LIKE ? OR p.crop_name LIKE ? OR u.name LIKE ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $rows = Database::select(
            "SELECT p.*, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    b.booking_number, c.name AS centre_name, c.code AS centre_code,
                    bc.quantity_kg AS booked_qty
             FROM procurements p
             INNER JOIN bookings b ON b.id = p.booking_id AND b.deleted_at IS NULL
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             INNER JOIN users u ON u.id = p.user_id
             LEFT JOIN booking_crops bc ON bc.id = p.booking_crop_id
             WHERE {$where}
             ORDER BY p.id DESC",
            $params
        );

        return [
            'count' => count($rows),
            'items' => array_map(fn($p) => $this->present($p), $rows),
        ];
    }

    public function show(array $actor, int $id): array
    {
        $proc = $this->requireProcurement($id);
        $this->assertCentreScope($actor, (int) $proc['centre_id']);
        return $this->present($this->withDetails($proc));
    }

    public function farmerList(array $actor, array $filters): array
    {
        $filters = $this->validator->listFilters($filters);
        $rows = $this->procurements->allForUserScoped((int) $actor['id'], $filters);
        return [
            'count' => count($rows),
            'items' => array_map(fn($p) => $this->present($p), $rows),
        ];
    }

    public function farmerShow(array $actor, int $id): array
    {
        $proc = $this->requireProcurement($id);
        if ((int) $proc['user_id'] !== (int) $actor['id']) {
            throw new NotFoundException('PROC_NOT_FOUND', 'Procurement not found');
        }
        return $this->present($this->withDetails($proc));
    }

    public function approvalList(array $actor, array $filters): array
    {
        $filters = $this->validator->approvalListFilters($filters);
        $centreIds = $this->scope->centreIdsFor($actor);

        if (empty($centreIds)) {
            return ['count' => 0, 'items' => []];
        }

        $placeholders = implode(',', array_fill(0, count($centreIds), '?'));
        $where = "p.centre_id IN ({$placeholders}) AND p.deleted_at IS NULL";
        $params = $centreIds;

        if (!empty($filters['centre_id'])) {
            if (!in_array((int) $filters['centre_id'], $centreIds, true)) {
                return ['count' => 0, 'items' => []];
            }
            $where .= ' AND p.centre_id = ?';
            $params[] = (int) $filters['centre_id'];
        }

        $status = !empty($filters['status']) ? (string) $filters['status'] : Procurement::STATUS_PENDING_APPROVAL;
        $where .= ' AND p.status = ?';
        $params[] = $status;

        $rows = Database::select(
            "SELECT p.*, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    b.booking_number, c.name AS centre_name, c.code AS centre_code,
                    bc.quantity_kg AS booked_qty
             FROM procurements p
             INNER JOIN bookings b ON b.id = p.booking_id AND b.deleted_at IS NULL
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             INNER JOIN users u ON u.id = p.user_id
             LEFT JOIN booking_crops bc ON bc.id = p.booking_crop_id
             WHERE {$where}
             ORDER BY p.id ASC",
            $params
        );

        return [
            'count' => count($rows),
            'items' => array_map(fn($p) => $this->present($p), $rows),
        ];
    }

    public function approvalShow(array $actor, int $id): array
    {
        $proc = $this->requireProcurement($id);
        $this->assertCentreScope($actor, (int) $proc['centre_id']);
        return $this->present($this->withDetails($proc));
    }

    public function approvalRequiredForCentre(int $centreId): bool
    {
        return $this->settings->getBool('procurement.approval_required', true);
    }

    private function assertCentreScope(array $actor, int $centreId): void
    {
        if (!$this->scope->canAccessCentre($actor, $centreId)) {
            throw new CentreMismatchException();
        }
    }

    private function bookedQtyFor(int $bookingCropId): ?float
    {
        if ($bookingCropId <= 0) {
            return null;
        }
        $row = Database::selectOne(
            "SELECT quantity_kg FROM booking_crops WHERE id = ? LIMIT 1",
            [$bookingCropId]
        );
        return $row !== null ? (float) $row['quantity_kg'] : null;
    }

    private function replacePhotos(int $procurementId, array $photoIds): void
    {
        if (empty($photoIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
        $files = Database::select(
            "SELECT id FROM files WHERE id IN ({$placeholders}) AND status = 'ACTIVE' AND deleted_at IS NULL",
            $photoIds
        );
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            Database::query(
                "INSERT IGNORE INTO file_references (file_id, source_type, source_id) VALUES (?, ?, ?)",
                [(int) $file['id'], self::PHOTO_SOURCE_TYPE, $procurementId]
            );
        }
    }

    private function fireVerificationPaymentHook(int $procurementId, int $actorId): void
    {
        $proc = $this->procurements->find($procurementId);
        $payment = (new PaymentService())->createForProcurement($procurementId);

        $newValue = [];
        if ($payment !== null) {
            $newValue['payment'] = [
                'id' => (int) $payment['id'],
                'amount' => $payment['amount'],
                'status' => $payment['status'],
            ];
        }

        $this->audit->log([
            'user_id' => $actorId,
            'action' => 'PROC_VERIFIED_PAYMENT_HOOK',
            'module' => 'PROCUREMENT',
            'entity_type' => 'procurement',
            'entity_id' => $procurementId,
            'new_value' => $newValue,
        ]);

        if ($proc !== null) {
            try {
                (new NotificationService())->dispatch('procurement_completed', (int) $proc['user_id'], [
                    'entity_id' => $procurementId,
                    'entity_type' => 'procurement',
                    'crop' => $proc['crop_name'] ?? '',
                    'qty' => $proc['accepted_weight'] ?? '0',
                ]);
            } catch (\Throwable $ignored) {
            }
        }
    }

    private function nextProcurementNumber(): string
    {
        $row = Database::selectOne(
            "SELECT COALESCE(MAX(id), 0) + 1 AS next FROM procurements"
        );
        $next = (int) $row['next'];
        return 'PROC-' . date('Ymd') . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function requireProcurement(int $id): array
    {
        $proc = $this->procurements->find($id);
        if ($proc === null) {
            throw new NotFoundException('PROC_NOT_FOUND', 'Procurement not found');
        }
        return $proc;
    }

    private function requireQueueEntry(int $entryId): array
    {
        $entry = Database::selectOne(
            "SELECT * FROM queue_entries WHERE id = ? LIMIT 1",
            [$entryId]
        );
        if ($entry === null) {
            throw new NotFoundException('ENTRY_NOT_ELIGIBLE', 'Queue entry not found');
        }
        return $entry;
    }

    private function withDetails(array $proc): array
    {
        $details = Database::selectOne(
            "SELECT p.*, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    b.booking_number, c.name AS centre_name, c.code AS centre_code,
                    bc.quantity_kg AS booked_qty, bc.variety AS booked_variety
             FROM procurements p
             INNER JOIN bookings b ON b.id = p.booking_id AND b.deleted_at IS NULL
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             INNER JOIN users u ON u.id = p.user_id
             LEFT JOIN booking_crops bc ON bc.id = p.booking_crop_id
             WHERE p.id = ? AND p.deleted_at IS NULL
             LIMIT 1",
            [(int) $proc['id']]
        );

        return $details !== null ? $details : $proc;
    }

    private function present(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'procurement_number' => $p['procurement_number'] ?? null,
            'booking_id' => (int) $p['booking_id'],
            'booking_crop_id' => isset($p['booking_crop_id']) && $p['booking_crop_id'] !== null ? (int) $p['booking_crop_id'] : null,
            'booking_number' => $p['booking_number'] ?? null,
            'centre_id' => (int) $p['centre_id'],
            'centre_name' => $p['centre_name'] ?? null,
            'centre_code' => $p['centre_code'] ?? null,
            'farmer' => isset($p['farmer_name']) ? [
                'id' => isset($p['user_id']) ? (int) $p['user_id'] : null,
                'name' => (string) $p['farmer_name'],
                'mobile' => (string) ($p['farmer_mobile'] ?? ''),
            ] : null,
            'crop_name' => (string) $p['crop_name'],
            'variety' => $p['variety'] ?? null,
            'booked_qty' => isset($p['booked_qty']) && $p['booked_qty'] !== null ? (float) $p['booked_qty'] : null,
            'accepted_weight' => isset($p['accepted_weight']) ? (float) $p['accepted_weight'] : 0.0,
            'damaged_qty' => isset($p['damaged_qty']) ? (float) $p['damaged_qty'] : 0.0,
            'quantity_kg' => (float) $p['quantity_kg'],
            'grade' => $p['quality_grade'] ?? null,
            'moisture_pct' => $p['moisture_percent'] !== null ? (float) $p['moisture_percent'] : null,
            'quality_notes' => $p['notes'] ?? null,
            'operator_note' => $p['operator_note'] ?? null,
            'status' => (string) $p['status'],
            'rejection_reason' => $p['rejection_reason'] ?? null,
            'approved_amount' => isset($p['approved_amount']) && $p['approved_amount'] !== null ? (float) $p['approved_amount'] : null,
            'approved_by' => isset($p['approved_by']) && $p['approved_by'] !== null ? (int) $p['approved_by'] : null,
            'approved_at' => $p['approved_at'] ?? null,
            'verified_at' => $p['verified_at'] ?? null,
            'verified_by' => isset($p['verified_by']) && $p['verified_by'] !== null ? (int) $p['verified_by'] : null,
            'created_at' => $p['created_at'] ?? null,
            'updated_at' => $p['updated_at'] ?? null,
        ];
    }
}