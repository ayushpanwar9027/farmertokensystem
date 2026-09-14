<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\CentreMismatchException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\Payment;
use App\Models\Procurement;
use App\Validators\PaymentValidator;

class PaymentService
{
    private Payment $payments;
    private Procurement $procurements;
    private RateService $rates;
    private SettingService $settings;
    private PaymentValidator $validator;
    private AuditService $audit;
    private ScopeService $scope;

    public function __construct()
    {
        $this->payments = new Payment();
        $this->procurements = new Procurement();
        $this->rates = new RateService();
        $this->settings = new SettingService();
        $this->validator = new PaymentValidator();
        $this->audit = new AuditService();
        $this->scope = new ScopeService();
    }

    public function createForProcurement(int $procurementId): ?array
    {
        $proc = $this->procurements->byId($procurementId);
        if ($proc === null) {
            return null;
        }

        if ((string) $proc['status'] !== Procurement::STATUS_VERIFIED) {
            return null;
        }

        $existing = $this->payments->byProcurementId($procurementId);
        if ($existing !== null) {
            return $this->present($existing);
        }

        $acceptedWeight = isset($proc['accepted_weight']) ? (float) $proc['accepted_weight'] : 0.0;
        if ($acceptedWeight <= 0) {
            return null;
        }

        $cropName = (string) $proc['crop_name'];
        $centreId = (int) $proc['centre_id'];
        $date = date('Y-m-d');

        $rate = $this->rates->resolveRateByName($cropName, $centreId, $date);
        if ($rate <= 0) {
            $rateRow = Database::selectOne(
                "SELECT id FROM crops WHERE LOWER(name) = LOWER(?) LIMIT 1",
                [$cropName]
            );
            if ($rateRow !== null) {
                $rate = $this->rates->resolveRate((int) $rateRow['id'], $centreId, $date);
            }
        }

        $amount = round($acceptedWeight * $rate, 2);

        $paymentId = Database::insert('payments', [
            'procurement_id' => $procurementId,
            'centre_id' => $centreId,
            'user_id' => (int) $proc['user_id'],
            'amount' => $amount,
            'rate_per_kg' => $rate,
            'crop_name' => $cropName,
            'status' => Payment::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $payment = $this->payments->byId($paymentId);
        return $this->present($payment);
    }

    public function release(array $actor, int $id, array $data): array
    {
        $data = $this->validator->release($data);
        $payment = $this->requirePayment($id);
        $this->assertCentreScope($actor, (int) $payment['centre_id']);

        if ((string) $payment['status'] === Payment::STATUS_RELEASED) {
            if ((string) $payment['payment_reference'] === (string) $data['payment_reference']
                && (string) $payment['payment_method'] === (string) $data['method']) {
                return $this->present($payment);
            }
            throw new ConflictException('DUPLICATE_RELEASE', 'This payment is already released with a different reference');
        }

        if (!in_array((string) $payment['status'], Payment::ELIGIBLE_FOR_RELEASE, true)) {
            throw new ConflictException('PAYMENT_STATUS_INVALID', 'Payment cannot be released in its current status');
        }

        $rbac = new RbacService();
        try {
            $rbac->assertRoleLevel($actor, 50);
        } catch (AuthorizationException $e) {
            $allowOperatorRelease = $this->settings->getBool('payment.allow_operator_release', false);
            if (!$allowOperatorRelease) {
                throw new CentreMismatchException();
            }
        }

        $now = date('Y-m-d H:i:s');

        Database::update(
            'payments',
            [
                'status' => Payment::STATUS_RELEASED,
                'payment_method' => (string) $data['method'],
                'payment_reference' => (string) $data['payment_reference'],
                'released_by' => (int) $actor['id'],
                'released_at' => $now,
                'updated_at' => $now,
            ],
            'id = ? AND status IN ("PENDING","INITIATED")',
            [$id]
        );

        $updated = $this->payments->byId($id);
        if ((string) $updated['status'] !== Payment::STATUS_RELEASED) {
            throw new ConflictException('CONCURRENT_UPDATE', 'Payment was modified by another request');
        }

        $maskedRef = $this->maskReference((string) $data['payment_reference']);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PAYMENT_RELEASE',
            'module' => 'PAYMENTS',
            'entity_type' => 'payment',
            'entity_id' => $id,
            'new_value' => [
                'status' => Payment::STATUS_RELEASED,
                'amount' => $payment['amount'],
                'method' => $data['method'],
                'reference_masked' => $maskedRef,
            ],
        ]);

        $this->enqueueEvent($payment, 'payment_released');

        return $this->present($this->payments->byId($id));
    }

    public function cancel(array $actor, int $id, array $data): array
    {
        $data = $this->validator->cancel($data);
        $payment = $this->requirePayment($id);
        $this->assertCentreScope($actor, (int) $payment['centre_id']);

        if ((string) $payment['status'] === Payment::STATUS_RELEASED) {
            throw new ConflictException('PAYMENT_STATUS_INVALID', 'Released payments cannot be cancelled. Use the reversal approval path.');
        }

        if ((string) $payment['status'] === Payment::STATUS_REVERSED) {
            throw new ConflictException('PAYMENT_STATUS_INVALID', 'Payment is already reversed');
        }

        if (!in_array((string) $payment['status'], Payment::ELIGIBLE_FOR_CANCEL, true)) {
            throw new ConflictException('PAYMENT_STATUS_INVALID', 'Payment cannot be cancelled in its current status');
        }

        $now = date('Y-m-d H:i:s');

        Database::update(
            'payments',
            [
                'status' => Payment::STATUS_CANCELLED,
                'cancelled_by' => (int) $actor['id'],
                'cancelled_at' => $now,
                'updated_at' => $now,
            ],
            'id = ? AND status IN ("PENDING","INITIATED")',
            [$id]
        );

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PAYMENT_CANCEL',
            'module' => 'PAYMENTS',
            'entity_type' => 'payment',
            'entity_id' => $id,
            'new_value' => ['status' => Payment::STATUS_CANCELLED],
            'reason' => $data['reason'],
        ]);

        return $this->present($this->payments->byId($id));
    }

    public function reverse(array $actor, int $id, array $data): array
    {
        $data = $this->validator->reverse($data);
        $payment = $this->requirePayment($id);
        $this->assertCentreScope($actor, (int) $payment['centre_id']);

        if ((string) $payment['status'] !== Payment::STATUS_RELEASED) {
            throw new ConflictException('PAYMENT_STATUS_INVALID', 'Only released payments can be reversed');
        }

        $now = date('Y-m-d H:i:s');

        Database::update(
            'payments',
            [
                'status' => Payment::STATUS_REVERSED,
                'reversal_reason' => (string) $data['reason'],
                'reversed_by' => (int) $actor['id'],
                'reversed_at' => $now,
                'updated_at' => $now,
            ],
            'id = ? AND status = ?',
            [$id, Payment::STATUS_RELEASED]
        );

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PAYMENT_REVERSE',
            'module' => 'PAYMENTS',
            'entity_type' => 'payment',
            'entity_id' => $id,
            'old_value' => ['status' => Payment::STATUS_RELEASED],
            'new_value' => ['status' => Payment::STATUS_REVERSED],
            'reason' => $data['reason'],
        ]);

        $this->enqueueEvent($payment, 'payment_reversed');

        return $this->present($this->payments->byId($id));
    }

    public function operatorList(array $actor, array $filters): array
    {
        $filters = $this->validator->listFilters($filters);
        $centreIds = $this->scopeCentreIds($actor);

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
            $where .= ' AND (p.crop_name LIKE ? OR p.payment_reference LIKE ? OR u.name LIKE ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $rows = Database::select(
            "SELECT p.*, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    c.name AS centre_name, c.code AS centre_code,
                    pr.crop_name AS proc_crop_name, pr.accepted_weight, pr.quality_grade,
                    pr.procurement_number
             FROM payments p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             INNER JOIN procurements pr ON pr.id = p.procurement_id
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
        $payment = $this->requirePayment($id);
        $this->assertCentreScope($actor, (int) $payment['centre_id']);
        return $this->present($this->withDetails($payment));
    }

    public function adminList(array $actor, array $filters): array
    {
        $filters = $this->validator->listFilters($filters);
        $centreIds = $this->scopeCentreIds($actor);

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
            $where .= ' AND (p.crop_name LIKE ? OR p.payment_reference LIKE ? OR u.name LIKE ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $rows = Database::select(
            "SELECT p.*, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    c.name AS centre_name, c.code AS centre_code,
                    pr.crop_name AS proc_crop_name, pr.accepted_weight, pr.quality_grade,
                    pr.procurement_number
             FROM payments p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             INNER JOIN procurements pr ON pr.id = p.procurement_id
             WHERE {$where}
             ORDER BY p.id DESC",
            $params
        );

        return [
            'count' => count($rows),
            'items' => array_map(fn($p) => $this->present($p), $rows),
        ];
    }

    public function farmerStatement(array $actor): array
    {
        $rows = $this->payments->allForUserScoped((int) $actor['id']);
        return [
            'count' => count($rows),
            'items' => array_map(fn($p) => $this->present($p), $rows),
        ];
    }

    public function farmerShow(array $actor, int $id): array
    {
        $payment = $this->requirePayment($id);
        if ((int) $payment['user_id'] !== (int) $actor['id']) {
            throw new NotFoundException('PAYMENT_NOT_FOUND', 'Payment not found');
        }
        return $this->present($this->withDetails($payment));
    }

    private function requirePayment(int $id): array
    {
        $payment = $this->payments->byId($id);
        if ($payment === null) {
            throw new NotFoundException('PAYMENT_NOT_FOUND', 'Payment not found');
        }
        return $payment;
    }

    private function assertCentreScope(array $actor, int $centreId): void
    {
        if (!$this->scope->canAccessCentre($actor, $centreId)) {
            throw new CentreMismatchException();
        }
    }

    private function scopeCentreIds(array $actor): array
    {
        $centreIds = $this->scope->centreIdsFor($actor);
        if (!empty($centreIds)) {
            return array_map('intval', $centreIds);
        }

        $districtIds = $this->scope->districtIdsFor($actor);
        if (empty($districtIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($districtIds), '?'));
        $rows = Database::select(
            "SELECT id FROM procurement_centres
             WHERE district_id IN ({$placeholders}) AND deleted_at IS NULL",
            $districtIds
        );

        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    private function maskReference(string $ref): string
    {
        $len = strlen($ref);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - 4) . substr($ref, -4);
    }

    private function enqueueEvent(array $payment, string $eventType): void
    {
        $row = Database::selectOne(
            "SELECT mobile FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [(int) $payment['user_id']]
        );
        if ($row === null || empty($row['mobile'])) {
            return;
        }

        $notificationEvent = match ($eventType) {
            'payment_processing' => 'payment_processing',
            'payment_released', 'payment_paid' => 'payment_paid',
            'payment_reversed' => 'payment_failed',
            default => 'payment_processing',
        };

        $params = [
            'entity_id' => (int) $payment['id'],
            'entity_type' => 'payment',
            'user_id' => (int) $payment['user_id'],
            'crop' => $payment['crop_name'] ?? '',
            'amount' => $payment['amount'] ?? '0',
            'reference' => $payment['payment_reference'] ?? '',
        ];

        try {
            (new NotificationService())->dispatch($notificationEvent, (int) $payment['user_id'], $params);
        } catch (\Throwable $ignored) {
        }
    }

    private function withDetails(array $p): array
    {
        $details = Database::selectOne(
            "SELECT p.*, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    c.name AS centre_name, c.code AS centre_code,
                    pr.crop_name AS proc_crop_name, pr.accepted_weight, pr.quality_grade,
                    pr.procurement_number, pr.booking_id
             FROM payments p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             INNER JOIN procurements pr ON pr.id = p.procurement_id
             WHERE p.id = ? AND p.deleted_at IS NULL
             LIMIT 1",
            [(int) $p['id']]
        );
        return $details !== null ? $details : $p;
    }

    private function present(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'procurement_id' => (int) $p['procurement_id'],
            'procurement_number' => $p['procurement_number'] ?? null,
            'centre_id' => (int) $p['centre_id'],
            'centre_name' => $p['centre_name'] ?? null,
            'centre_code' => $p['centre_code'] ?? null,
            'user_id' => (int) $p['user_id'],
            'farmer' => isset($p['farmer_name']) ? [
                'id' => (int) $p['user_id'],
                'name' => (string) $p['farmer_name'],
                'mobile' => (string) ($p['farmer_mobile'] ?? ''),
            ] : null,
            'crop_name' => $p['crop_name'] ?? ($p['proc_crop_name'] ?? null),
            'accepted_weight' => isset($p['accepted_weight']) ? (float) $p['accepted_weight'] : null,
            'quality_grade' => $p['quality_grade'] ?? null,
            'rate_per_kg' => isset($p['rate_per_kg']) && $p['rate_per_kg'] !== null ? (float) $p['rate_per_kg'] : null,
            'amount' => (float) $p['amount'],
            'status' => (string) $p['status'],
            'payment_method' => $p['payment_method'] ?? null,
            'payment_reference' => $p['payment_reference'] ?? null,
            'released_by' => isset($p['released_by']) && $p['released_by'] !== null ? (int) $p['released_by'] : null,
            'released_at' => $p['released_at'] ?? null,
            'reversed_by' => isset($p['reversed_by']) && $p['reversed_by'] !== null ? (int) $p['reversed_by'] : null,
            'reversed_at' => $p['reversed_at'] ?? null,
            'reversal_reason' => $p['reversal_reason'] ?? null,
            'cancelled_by' => isset($p['cancelled_by']) && $p['cancelled_by'] !== null ? (int) $p['cancelled_by'] : null,
            'cancelled_at' => $p['cancelled_at'] ?? null,
            'failure_reason' => $p['failure_reason'] ?? null,
            'notes' => $p['notes'] ?? null,
            'created_at' => $p['created_at'] ?? null,
            'updated_at' => $p['updated_at'] ?? null,
        ];
    }
}
