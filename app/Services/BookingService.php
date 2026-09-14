<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Booking;
use App\Models\Farmer;
use App\Models\ProcurementCentre;
use App\Models\Slot;
use App\Models\Token;
use App\Validators\BookingValidator;

class BookingService
{
    private BookingValidator $validator;
    private Booking $bookings;
    private BookingCropService $cropService;
    private ScopeService $scope;
    private RbacService $rbac;
    private AuditService $audit;
    private SettingService $settings;
    private Slot $slots;
    private ProcurementCentre $centres;
    private Token $tokens;
    private TokenService $tokenService;
    private QueueService $queue;

    public function __construct()
    {
        $this->validator = new BookingValidator();
        $this->bookings = new Booking();
        $this->cropService = new BookingCropService();
        $this->scope = new ScopeService();
        $this->rbac = new RbacService();
        $this->audit = new AuditService();
        $this->settings = new SettingService();
        $this->slots = new Slot();
        $this->centres = new ProcurementCentre();
        $this->tokens = new Token();
        $this->tokenService = new TokenService();
        $this->queue = new QueueService();
    }

    public function create(array $actor, array $data): array
    {
        $data = $this->validator->create($data);

        $this->assertFarmerApproved($actor);

        $centreId = (int) $data['centre_id'];
        $slotId = (int) $data['slot_id'];
        $bookingDate = (string) $data['booking_date'];

        $centre = $this->requireActiveCentre($centreId);
        $slot = $this->requireBookableSlot($slotId, $centreId, $bookingDate);
        $this->assertBookingWindow($slot);

        $this->assertActiveLimit((int) $actor['id']);

        $lines = $this->cropService->resolve((array) $data['crops']);
        $totalQty = $this->cropService->totalQuantityKg($lines);
        $maxQty = $this->settings->getInt('max_quantity_kg_per_booking', 5000);
        if ($totalQty > $maxQty) {
            throw new ValidationException([
                'crops' => ['Total quantity exceeds the maximum of ' . $maxQty . ' kg per booking'],
            ]);
        }

        $autoConfirm = $this->settings->getBool('auto_confirm_on_payment', false);
        $status = $autoConfirm ? Booking::STATUS_CONFIRMED : Booking::STATUS_PENDING;

        $bookingNumber = $this->generateBookingNumber();

        $row = [
            'booking_number' => $bookingNumber,
            'user_id' => (int) $actor['id'],
            'centre_id' => $centreId,
            'slot_id' => $slotId,
            'date' => $bookingDate,
            'status' => $status,
            'crop_count' => count($lines),
            'total_quantity_kg' => $totalQty,
        ];

        Database::beginTransaction();
        try {
            $bookingId = $this->bookings->insert($row);
            $this->cropService->insertLines($bookingId, $lines);

            $this->reserveCapacity($slotId);

            $token = null;
            if ($status === Booking::STATUS_CONFIRMED) {
                $token = $this->issueToken($bookingId, $centre, $actor);
                $this->queue->enqueueFromBooking((int) $bookingId, $centreId, $bookingDate);
            }

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'BOOKING_CREATED',
                'module' => 'BOOKINGS',
                'entity_type' => 'booking',
                'entity_id' => $bookingId,
                'new_value' => [
                    'booking_number' => $bookingNumber,
                    'centre_id' => $centreId,
                    'slot_id' => $slotId,
                    'date' => $bookingDate,
                    'status' => $status,
                    'crop_count' => count($lines),
                    'total_quantity_kg' => $totalQty,
                ],
                'reason' => 'farmer_create_booking',
            ]);

            Database::commit();
        } catch (\PDOException $e) {
            Database::rollback();
            if (in_array((string) ($e->errorInfo[1] ?? ''), ['1062', '23000'], true)) {
                throw new ConflictException('DUPLICATE_BOOKING', 'You already have an active booking for this slot');
            }
            throw $e;
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        if ($status === Booking::STATUS_CONFIRMED) {
            try {
                $notifParams = [
                    'entity_id' => $bookingId,
                    'entity_type' => 'booking',
                    'centre' => $centre['name'] ?? '',
                    'date' => $bookingDate,
                    'slot' => (string) ($slot['start_time'] ?? ''),
                    'token' => $token['token_number'] ?? '',
                ];
                (new NotificationService())->dispatch('booking_confirmed', (int) $actor['id'], $notifParams);
            } catch (\Throwable $ignored) {
            }
        }

        $booking = $this->bookings->find($bookingId);
        return $this->details($booking, $centre, $slot, $token);
    }

    public function cancel(array $actor, array $booking, ?string $reason = null): array
    {
        $this->assertOwner($actor, $booking);

        if (!in_array((string) $booking['status'], Booking::ACTIVE_STATUSES, true)) {
            throw new ConflictException(
                'BOOKING_NOT_CANCELLABLE',
                'Only pending or confirmed bookings can be cancelled'
            );
        }

        $this->assertCancellationWindow($booking);

        return $this->doCancel($actor, $booking, 'FARMER', (string) ($reason ?? ''));
    }

    public function adminCancel(array $actor, array $booking, array $data): array
    {
        $data = $this->validator->adminCancel($data);

        if (!in_array((string) $booking['status'], Booking::ACTIVE_STATUSES, true)) {
            throw new ConflictException(
                'BOOKING_NOT_CANCELLABLE',
                'Only pending or confirmed bookings can be cancelled'
            );
        }

        return $this->doCancel($actor, $booking, 'STAFF', (string) $data['reason']);
    }

    public function show(array $actor, int $bookingId): array
    {
        $booking = $this->bookings->find($bookingId);
        if ($booking === null) {
            throw new NotFoundException('BOOKING_NOT_FOUND', 'Booking not found');
        }

        $role = strtoupper((string) ($actor['role'] ?? ''));
        if ($role === 'FARMER') {
            if ((int) $booking['user_id'] !== (int) $actor['id']) {
                throw new NotFoundException('BOOKING_NOT_FOUND', 'Booking not found');
            }
        } else {
            $this->scope->assertCentreScope($actor, (int) $booking['centre_id']);
        }

        $centre = $this->centres->find((int) $booking['centre_id']) ?? [];
        $slot = $this->slots->find((int) $booking['slot_id']) ?? [];
        $token = $this->bookingToken((int) $booking['id']);

        return $this->details($booking, $centre, $slot, $token);
    }

    public function list(array $actor, array $filters, int $page, int $perPage): array
    {
        $this->validator->listFilters($filters);

        $userId = (int) $actor['id'];
        $status = strtoupper((string) ($filters['status'] ?? ''));
        $dateFrom = (string) ($filters['date_from'] ?? $filters['from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? $filters['to'] ?? '');
        $q = trim((string) ($filters['q'] ?? ''));

        $where = ["b.user_id = ?", 'b.deleted_at IS NULL'];
        $params = [$userId];

        $this->applyCommons($where, $params, $status, $dateFrom, $dateTo, $q);

        return $this->paginate($where, $params, $page, $perPage);
    }

    public function adminList(array $actor, array $filters, int $page, int $perPage): array
    {
        $this->validator->listFilters($filters);

        $status = strtoupper((string) ($filters['status'] ?? ''));
        $dateFrom = (string) ($filters['date_from'] ?? $filters['from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? $filters['to'] ?? '');
        $q = trim((string) ($filters['q'] ?? ''));
        $centreId = isset($filters['centre_id']) && $filters['centre_id'] !== '' ? (int) $filters['centre_id'] : null;

        $where = ['b.deleted_at IS NULL'];
        $params = [];

        $this->applyScope($actor, $where, $params, $centreId);
        $this->applyCommons($where, $params, $status, $dateFrom, $dateTo, $q);

        return $this->paginate($where, $params, $page, $perPage);
    }

    public function cropsFor(array $actor, array $booking): array
    {
        $role = strtoupper((string) ($actor['role'] ?? ''));
        if ($role === 'FARMER') {
            if ((int) $booking['user_id'] !== (int) $actor['id']) {
                throw new NotFoundException('BOOKING_NOT_FOUND', 'Booking not found');
            }
        } else {
            $this->scope->assertCentreScope($actor, (int) $booking['centre_id']);
        }

        return $this->cropService->listForBooking((int) $booking['id']);
    }

    public function myToken(array $actor): ?array
    {
        $this->assertFarmerApproved($actor);
        $token = $this->tokens->activeForUser((int) $actor['id']);
        if ($token === null) {
            return null;
        }
        return [
            'token_number' => $token['token_number'],
            'booking_number' => $token['booking_number'],
            'booking_date' => $token['booking_date'],
            'centre' => [
                'id' => (int) $token['centre_id'],
                'name' => $token['centre_name'],
                'code' => $token['centre_code'],
            ],
            'slot' => [
                'date' => $token['booking_date'],
                'start_time' => $token['start_time'],
                'end_time' => $token['end_time'],
            ],
            'status' => $token['status'],
            'issued_at' => $token['issued_at'],
            'qr_data' => $token['qr_data'],
        ];
    }

    private function doCancel(array $actor, array $booking, string $by, string $reason): array
    {
        $bookingId = (int) $booking['id'];

        Database::beginTransaction();
        try {
            $updates = [
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $by,
                'cancelled_by_user_id' => (int) $actor['id'],
            ];
            if ($reason !== '') {
                $updates['cancellation_reason'] = $reason;
            }
            $affected = Database::update(
                'bookings',
                $updates,
                'id = ? AND status IN ("PENDING","CONFIRMED")',
                [$bookingId]
            );
            if ($affected === 0) {
                throw new ConflictException('CONCURRENT_UPDATE', 'Booking was cancelled by another request');
            }

            $this->cropService->cancelLines($bookingId);

            $token = $this->bookingToken($bookingId);
            if ($token !== null && in_array($token['status'], [Token::STATUS_ACTIVE], true)) {
                Database::update(
                    'tokens',
                    ['status' => Token::STATUS_CANCELLED, 'revoked_at' => date('Y-m-d H:i:s'), 'revoked_reason' => 'booking_cancelled'],
                    'id = ?',
                    [(int) $token['id']]
                );
            }

            $this->queue->markBookingCancelled($bookingId);

            $this->releaseCapacity((int) $booking['slot_id']);

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'BOOKING_CANCELLED',
                'module' => 'BOOKINGS',
                'entity_type' => 'booking',
                'entity_id' => $bookingId,
                'old_value' => ['status' => $booking['status']],
                'new_value' => ['status' => Booking::STATUS_CANCELLED, 'cancelled_by' => $by],
                'reason' => $reason !== '' ? $reason : ($by === 'STAFF' ? 'admin_cancel_booking' : 'farmer_cancel_booking'),
            ]);

            Database::commit();
        } catch (\PDOException $e) {
            Database::rollback();
            if (in_array((string) ($e->errorInfo[1] ?? ''), ['1062', '23000'], true)) {
                throw new ConflictException('CONCURRENT_UPDATE', 'Booking cancellation conflicted with another request');
            }
            throw $e;
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        try {
            $notifParams = [
                'entity_id' => $bookingId,
                'entity_type' => 'booking',
                'centre' => $centre['name'] ?? '',
                'date' => $booking['date'] ?? '',
            ];
            (new NotificationService())->dispatch('booking_cancelled', (int) $booking['user_id'], $notifParams);
        } catch (\Throwable $ignored) {
        }

        $updated = $this->bookings->find($bookingId);
        $centre = $this->centres->find((int) $booking['centre_id']) ?? [];
        $slot = $this->slots->find((int) $booking['slot_id']) ?? [];

        return $this->details($updated, $centre, $slot, $this->bookingToken($bookingId));
    }

    private function reserveCapacity(int $slotId): void
    {
        $sql = "UPDATE slots
                SET booked_count = booked_count + 1,
                    status = CASE WHEN booked_count + 1 >= capacity THEN 'FULL' ELSE status END,
                    updated_at = NOW()
                WHERE id = ? AND status = 'ACTIVE' AND booked_count < capacity AND deleted_at IS NULL";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$slotId]);

        if ($stmt->rowCount() === 0) {
            throw new ConflictException('SLOT_FULL', 'This slot is no longer available');
        }
    }

    private function releaseCapacity(int $slotId): void
    {
        $sql = "UPDATE slots
                SET booked_count = IF(booked_count > 0, booked_count - 1, 0),
                    status = CASE WHEN status = 'FULL' AND IF(booked_count > 0, booked_count - 1, 0) < capacity THEN 'ACTIVE' ELSE status END,
                    updated_at = NOW()
                WHERE id = ? AND deleted_at IS NULL";
        Database::getConnection()->prepare($sql)->execute([$slotId]);
    }

    private function issueToken(int $bookingId, array $centre, array $actor): array
    {
        $year = (int) date('Y');
        $seq = $this->nextTokenSequence((string) $centre['code'], $year);
        $display = sprintf('%s-%04d-%05d', strtoupper((string) $centre['code']), $year, $seq);
        $hash = hash('sha256', $display);

        try {
            $tokenId = Database::insert('tokens', [
                'booking_id' => $bookingId,
                'token_number' => $display,
                'token_hash' => $hash,
                'qr_data' => TokenService::qrData($display),
                'issued_at' => date('Y-m-d H:i:s'),
                'issued_by' => 'SYSTEM',
                'status' => Token::STATUS_ACTIVE,
            ]);
        } catch (\Throwable $e) {
            $code = $e instanceof \PDOException ? (string) $e->errorInfo[1] : (string) $e->getCode();
            if (in_array($code, ['1062', '23000'], true)) {
                throw new ConflictException('TOKEN_EXISTS', 'Unable to generate a unique token, please retry');
            }
            throw $e;
        }

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'TOKEN_ISSUED',
            'module' => 'TOKENS',
            'entity_type' => 'token',
            'entity_id' => $tokenId,
            'new_value' => ['booking_id' => $bookingId, 'token_number' => $display],
            'reason' => 'booking_confirmed',
        ]);

        $row = $this->tokens->find($tokenId);
        $row['token_hash'] = $hash;
        return $row;
    }

    private function nextTokenSequence(string $centreCode, int $year): int
    {
        $prefix = strtoupper($centreCode) . '-' . $year . '-';
        $rows = Database::select(
            "SELECT token_number FROM tokens WHERE token_number LIKE ?",
            [$prefix . '%']
        );
        $max = 0;
        foreach ($rows as $r) {
            if (preg_match('/-(\d{5})$/', (string) $r['token_number'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $max + 1;
    }

    private function generateBookingNumber(): string
    {
        $date = date('Ymd');
        $dateKey = date('Y-m-d');
        $seq = $this->bookings->nextBookingSequence($dateKey);
        $number = 'BK-' . $date . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

        for ($i = 0; $i < 5; $i++) {
            $existing = Database::selectOne(
                "SELECT 1 FROM bookings WHERE booking_number = ? LIMIT 1",
                [$number]
            );
            if ($existing === null) {
                return $number;
            }
            $seq++;
            $number = 'BK-' . $date . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        }

        throw new ConflictException('CONCURRENT_UPDATE', 'Could not allocate a booking number, please retry');
    }

    private function assertFarmerApproved(array $actor): void
    {
        if (strtoupper((string) ($actor['role'] ?? '')) !== 'FARMER') {
            throw new AuthorizationException('Only farmers can create bookings');
        }

        if ((string) ($actor['verification_status'] ?? '') !== 'APPROVED') {
            throw new ConflictException('FARMER_NOT_APPROVED', 'Your farmer profile is not approved yet');
        }

        $farmer = (new Farmer())->byUserId((int) $actor['id']);
        if ($farmer === null || (string) ($farmer['verification_status'] ?? '') !== 'APPROVED') {
            throw new ConflictException('FARMER_NOT_APPROVED', 'Your farmer profile is not approved yet');
        }
    }

    private function requireActiveCentre(int $centreId): array
    {
        $centre = $this->centres->find($centreId);
        if ($centre === null) {
            throw new NotFoundException('CENTRE_NOT_FOUND', 'Procurement centre not found');
        }
        if ((string) $centre['status'] !== ProcurementCentre::STATUS_ACTIVE) {
            throw new BadRequestException('CENTRE_INACTIVE', 'This procurement centre is not active');
        }
        return $centre;
    }

    private function requireBookableSlot(int $slotId, int $centreId, string $bookingDate): array
    {
        $slot = $this->slots->find($slotId);
        if ($slot === null || (int) $slot['centre_id'] !== $centreId) {
            throw new BadRequestException('INVALID_SLOT', 'Invalid slot for this centre');
        }

        if (!in_array((string) $slot['status'], [Slot::STATUS_ACTIVE, Slot::STATUS_FULL], true)) {
            throw new BadRequestException('INVALID_SLOT', 'This slot is not available');
        }

        if ((string) $slot['date'] !== $bookingDate) {
            throw new BadRequestException('INVALID_SLOT', 'The slot does not belong to the requested date');
        }

        if (strtotime($bookingDate) < strtotime(date('Y-m-d'))) {
            throw new BadRequestException('BOOKING_WINDOW_CLOSED', 'You cannot book for a past date');
        }

        $horizon = max(1, $this->settings->getInt('booking.horizon_days', 7));
        $maxDate = date('Y-m-d', strtotime(date('Y-m-d') . ' + ' . $horizon . ' days'));
        if (strtotime($bookingDate) > strtotime($maxDate)) {
            throw new BadRequestException('BOOKING_WINDOW_CLOSED', 'Booking date is beyond the allowed horizon of ' . $horizon . ' days');
        }

        return $slot;
    }

    private function assertBookingWindow(array $slot): void
    {
        $minLeadHours = max(0, $this->settings->getInt('booking.min_lead_hours', 2));
        $slotStart = strtotime((string) $slot['date'] . ' ' . (string) $slot['start_time']);
        $earliest = time() + ($minLeadHours * 3600);
        if ($slotStart < $earliest) {
            throw new BadRequestException(
                'BOOKING_WINDOW_CLOSED',
                'Bookings must be made at least ' . $minLeadHours . ' hours before the slot starts'
            );
        }
    }

    private function assertActiveLimit(int $userId): void
    {
        $maxActive = max(1, $this->settings->getInt('booking.max_active', 1));
        $count = $this->bookings->countActiveForUser($userId);
        if ($count >= $maxActive) {
            throw new ConflictException(
                'DUPLICATE_BOOKING',
                'You already have ' . $count . ' active booking(s). Cancel it before creating a new one'
            );
        }
    }

    private function assertCancellationWindow(array $booking): void
    {
        $slot = $this->slots->find((int) $booking['slot_id']);
        $leadMinutes = max(0, $this->settings->getInt('booking.cancel_lead_minutes', 120));
        $slotStart = strtotime((string) $booking['date'] . ' ' . (string) ($slot['start_time'] ?? '00:00'));
        $cutoff = $slotStart - ($leadMinutes * 60);

        if (time() >= $cutoff) {
            throw new ConflictException(
                'CANCELLATION_WINDOW_CLOSED',
                'Cancellation is no longer allowed. Contact the centre for assistance'
            );
        }
    }

    private function assertOwner(array $actor, array $booking): void
    {
        if ((int) $booking['user_id'] !== (int) $actor['id']) {
            throw new NotFoundException('BOOKING_NOT_FOUND', 'Booking not found');
        }
    }

    private function bookingToken(int $bookingId): ?array
    {
        return $this->tokens->byBookingId($bookingId);
    }

    private function applyCommons(array &$where, array &$params, string $status, string $dateFrom, string $dateTo, string $q): void
    {
        if ($status !== '') {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }
        if ($dateFrom !== '') {
            $where[] = 'b.date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'b.date <= ?';
            $params[] = $dateTo;
        }
        if ($q !== '') {
            $where[] = '(b.booking_number LIKE ? OR b.user_id IN (SELECT id FROM users WHERE mobile LIKE ?))';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
    }

    private function applyScope(array $actor, array &$where, array &$params, ?int $centreId): void
    {
        $scope = $this->scope->scopeFor($actor);
        $scopeType = $scope['type'] ?? 'none';

        if ($scopeType === 'district') {
            if ($centreId !== null) {
                $this->scope->assertCentreScope($actor, $centreId);
                $where[] = 'b.centre_id = ?';
                $params[] = $centreId;
            } elseif (!empty($scope['district_ids'])) {
                $placeholders = implode(',', array_fill(0, count($scope['district_ids']), '?'));
                $where[] = 'c.district_id IN (' . $placeholders . ')';
                $params = array_merge($params, $scope['district_ids']);
            } else {
                $where[] = '1 = 0';
            }
        } elseif ($scopeType === 'centre') {
            if ($centreId !== null) {
                $this->scope->assertCentreScope($actor, $centreId);
                $where[] = 'b.centre_id = ?';
                $params[] = $centreId;
            } elseif (!empty($scope['centre_ids'])) {
                $placeholders = implode(',', array_fill(0, count($scope['centre_ids']), '?'));
                $where[] = 'b.centre_id IN (' . $placeholders . ')';
                $params = array_merge($params, $scope['centre_ids']);
            } else {
                $where[] = '1 = 0';
            }
        } elseif ($scopeType === 'all') {
            if ($centreId !== null) {
                $where[] = 'b.centre_id = ?';
                $params[] = $centreId;
            }
        } else {
            $where[] = '1 = 0';
        }
    }

    private function paginate(array $where, array $params, int $page, int $perPage): array
    {
        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM bookings b
             INNER JOIN procurement_centres c ON c.id = b.centre_id
             WHERE {$whereSql}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT b.*,
                    c.name AS centre_name, c.code AS centre_code,
                    s.start_time, s.end_time
             FROM bookings b
             INNER JOIN procurement_centres c ON c.id = b.centre_id
             INNER JOIN slots s ON s.id = b.slot_id
             WHERE {$whereSql}
             ORDER BY b.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'booking_number' => $row['booking_number'],
                'date' => $row['date'],
                'status' => $row['status'],
                'crop_count' => (int) $row['crop_count'],
                'total_quantity_kg' => (float) $row['total_quantity_kg'],
                'centre' => [
                    'id' => (int) $row['centre_id'],
                    'name' => $row['centre_name'],
                    'code' => $row['centre_code'],
                ],
                'slot' => [
                    'id' => (int) $row['slot_id'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                ],
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ];
    }

    private function details(array $booking, array $centre, array $slot, ?array $token): array
    {
        $result = [
            'id' => (int) $booking['id'],
            'booking_number' => $booking['booking_number'],
            'date' => $booking['date'],
            'status' => $booking['status'],
            'crop_count' => (int) $booking['crop_count'],
            'total_quantity_kg' => (float) $booking['total_quantity_kg'],
            'created_at' => $booking['created_at'],
            'cancelled_at' => $booking['cancelled_at'],
        ];

        if (!empty($centre)) {
            $result['centre'] = [
                'id' => (int) $centre['id'],
                'name' => $centre['name'] ?? '',
                'code' => $centre['code'] ?? '',
            ];
        }

        if (!empty($slot)) {
            $result['slot'] = [
                'id' => (int) $slot['id'],
                'date' => $slot['date'] ?? $booking['date'],
                'start_time' => $slot['start_time'] ?? null,
                'end_time' => $slot['end_time'] ?? null,
            ];
        }

        $result['crops'] = $this->cropService->listForBooking((int) $booking['id']);

        if ($token !== null) {
            $result['token'] = [
                'token_number' => $token['token_number'],
                'qr_data' => $token['qr_data'],
                'status' => $token['status'],
                'issued_at' => $token['issued_at'],
            ];
        }

        return $result;
    }
}
