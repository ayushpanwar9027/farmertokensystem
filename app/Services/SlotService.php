<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\ProcurementCentre;
use App\Models\Slot;
use App\Validators\SlotValidator;

class SlotService
{
    private SlotValidator $validator;
    private ScopeService $scope;
    private RbacService $rbac;
    private AuditService $audit;
    private Slot $slots;
    private ProcurementCentre $centres;
    private SettingService $settings;

    public function __construct()
    {
        $this->validator = new SlotValidator();
        $this->scope = new ScopeService();
        $this->rbac = new RbacService();
        $this->audit = new AuditService();
        $this->slots = new Slot();
        $this->centres = new ProcurementCentre();
        $this->settings = new SettingService();
    }

    public function create(array $actor, array $data): array
    {
        $data = $this->validator->create($data);

        $centreId = (int) $data['centre_id'];
        $this->scope->assertCentreScope($actor, $centreId);

        $centre = $this->requireActiveCentre($centreId);
        $date = (string) $data['date'];
        $startTime = (string) $data['start_time'];
        $endTime = (string) $data['end_time'];

        $this->assertFutureDate($date);
        $this->assertTimeRange($centre, $date, $startTime, $endTime);
        $this->assertGranularity($startTime);
        $this->assertGranularity($endTime);

        $capacity = (int) ($data['capacity'] ?? $this->settings->getInt('slot.default_capacity', 10));
        $capacity = max(1, $capacity);

        if ($this->slots->findByCentreDateTime($centreId, $date, $startTime, $endTime) !== null) {
            throw new ConflictException('SLOT_EXISTS', 'A slot already exists for this centre, date and time');
        }

        $row = [
            'centre_id' => $centreId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'capacity' => $capacity,
            'booked_count' => 0,
            'status' => Slot::STATUS_ACTIVE,
            'created_by' => (int) $actor['id'],
        ];

        Database::beginTransaction();
        try {
            $slotId = $this->slots->insert($row);
            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'SLOT_CREATED',
                'module' => 'SLOTS',
                'entity_type' => 'slot',
                'entity_id' => $slotId,
                'new_value' => $row,
                'reason' => 'admin_create_slot',
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $slot = $this->slots->find($slotId);
        return $this->details($slot);
    }

    public function update(array $actor, array $slot, array $data): array
    {
        $data = $this->validator->update($data);

        $this->scope->assertCentreScope($actor, (int) $slot['centre_id']);

        $updates = [];

        if (array_key_exists('capacity', $data) && $data['capacity'] !== null && $data['capacity'] !== '') {
            $capacity = (int) $data['capacity'];
            $booked = $this->slots->bookedCount((int) $slot['id']);
            if ($capacity < $booked) {
                throw new ConflictException(
                    'CAPACITY_BELOW_BOOKED',
                    'Capacity cannot be set below the current booked count (' . $booked . ')'
                );
            }
            $updates['capacity'] = $capacity;
        }

        $updateTime = false;
        $date = $slot['date'];
        $startTime = $slot['start_time'];
        $endTime = $slot['end_time'];

        if (array_key_exists('date', $data) && $data['date'] !== null && $data['date'] !== '') {
            $date = (string) $data['date'];
            $updateTime = true;
        }
        if (array_key_exists('start_time', $data) && $data['start_time'] !== null && $data['start_time'] !== '') {
            $startTime = (string) $data['start_time'];
            $updateTime = true;
        }
        if (array_key_exists('end_time', $data) && $data['end_time'] !== null && $data['end_time'] !== '') {
            $endTime = (string) $data['end_time'];
            $updateTime = true;
        }

        if ($updateTime) {
            if ($date !== (string) $slot['date'] || $startTime !== $slot['start_time'] || $endTime !== $slot['end_time']) {
                $centre = $this->requireActiveCentre((int) $slot['centre_id']);
                $this->assertFutureDate($date);
                $this->assertTimeRange($centre, $date, $startTime, $endTime);
                $this->assertGranularity($startTime);
                $this->assertGranularity($endTime);

                $existing = $this->slots->findByCentreDateTime(
                    (int) $slot['centre_id'],
                    $date,
                    $startTime,
                    $endTime
                );
                if ($existing !== null && (int) $existing['id'] !== (int) $slot['id']) {
                    throw new ConflictException('SLOT_EXISTS', 'A slot already exists for this centre, date and time');
                }
            }
            $updates['date'] = $date;
            $updates['start_time'] = $startTime;
            $updates['end_time'] = $endTime;
        }

        $status = null;
        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $status = strtoupper((string) $data['status']);
        }

        if ($status === Slot::STATUS_CANCELLED) {
            $this->assertCancellable($actor, $slot);
            $updates['status'] = Slot::STATUS_CANCELLED;
        } elseif ($status !== null && $status !== (string) $slot['status']) {
            if ($status !== Slot::STATUS_ACTIVE && $status !== Slot::STATUS_INACTIVE) {
                throw new BadRequestException('INVALID_STATUS', 'Invalid slot status transition');
            }
            $updates['status'] = $status;
        }

        if (empty($updates)) {
            throw new ValidationException(['data' => ['No valid fields were provided to update']]);
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');

        Database::beginTransaction();
        try {
            Database::update('slots', $updates, 'id = ?', [(int) $slot['id']]);

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'SLOT_UPDATED',
                'module' => 'SLOTS',
                'entity_type' => 'slot',
                'entity_id' => (int) $slot['id'],
                'old_value' => array_intersect_key($slot, $updates),
                'new_value' => $updates,
                'reason' => 'admin_update_slot',
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return $this->details($this->slots->find((int) $slot['id']));
    }

    public function cancel(array $actor, array $slot): array
    {
        $this->scope->assertCentreScope($actor, (int) $slot['centre_id']);
        $this->assertCancellable($actor, $slot);

        $updates = ['status' => Slot::STATUS_CANCELLED, 'updated_at' => date('Y-m-d H:i:s')];
        Database::update('slots', $updates, 'id = ?', [(int) $slot['id']]);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'SLOT_CANCELLED',
            'module' => 'SLOTS',
            'entity_type' => 'slot',
            'entity_id' => (int) $slot['id'],
            'old_value' => ['status' => $slot['status']],
            'new_value' => ['status' => Slot::STATUS_CANCELLED],
            'reason' => 'admin_cancel_slot',
        ]);

        return $this->details($this->slots->find((int) $slot['id']));
    }

    public function delete(array $actor, array $slot): void
    {
        $this->scope->assertCentreScope($actor, (int) $slot['centre_id']);

        if ((string) $slot['status'] !== Slot::STATUS_ACTIVE) {
            throw new ConflictException(
                'SLOT_NOT_DELETABLE',
                'Only slots in AVAILABLE state can be deleted'
            );
        }

        if ($this->slots->bookedCount((int) $slot['id']) > 0) {
            throw new ConflictException(
                'SLOT_HAS_BOOKINGS',
                'Cannot delete a slot that has bookings'
            );
        }

        Database::beginTransaction();
        try {
            $this->slots->delete((int) $slot['id']);

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'SLOT_DELETED',
                'module' => 'SLOTS',
                'entity_type' => 'slot',
                'entity_id' => (int) $slot['id'],
                'old_value' => [
                    'date' => $slot['date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'status' => $slot['status'],
                ],
                'reason' => 'admin_delete_slot',
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function show(?array $actor, int $slotId): array
    {
        $slot = $this->slots->find($slotId);
        if ($slot === null) {
            throw new NotFoundException('SLOT_NOT_FOUND', 'Slot not found');
        }
        if ($actor !== null) {
            $this->scope->assertCentreScope($actor, (int) $slot['centre_id']);
        }
        return $this->details($slot);
    }

    public function bookableList(array $filters): array
    {
        $centreId = isset($filters['centre_id']) && $filters['centre_id'] !== '' ? (int) $filters['centre_id'] : null;
        $date = trim((string) ($filters['date'] ?? ''));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        $today = date('Y-m-d');
        $horizon = max(1, $this->settings->getInt('slot.horizon_days', 7));
        $maxDate = date('Y-m-d', strtotime($today . ' + ' . $horizon . ' days'));

        $where = ["s.status = 'ACTIVE'", 's.deleted_at IS NULL', 's.date >= ?'];
        $params = [$today];

        $where[] = 'c.status = ?';
        $params[] = ProcurementCentre::STATUS_ACTIVE;

        $where[] = 'c.deleted_at IS NULL';

        if ($centreId !== null) {
            $where[] = 's.centre_id = ?';
            $params[] = $centreId;
        }
        if ($date !== '') {
            $where[] = 's.date = ?';
            $params[] = $date;
        } else {
            $where[] = 's.date <= ?';
            $params[] = $maxDate;
        }
        if ($from !== '') {
            $where[] = 's.date >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 's.date <= ?';
            $params[] = $to;
        }

        $whereSql = implode(' AND ', $where);

        $rows = Database::select(
            "SELECT s.*, c.name AS centre_name, c.code AS centre_code
             FROM slots s
             INNER JOIN procurement_centres c ON c.id = s.centre_id
             WHERE {$whereSql}
             ORDER BY s.date ASC, s.start_time ASC",
            $params
        );

        return array_map(fn($row) => $this->details($row, true), $rows);
    }

    public function list(?array $actor, array $filters, int $page, int $perPage): array
    {
        $this->validator->listFilters($filters);

        $where = ['s.deleted_at IS NULL'];
        $params = [];

        $centreId = isset($filters['centre_id']) && $filters['centre_id'] !== '' ? (int) $filters['centre_id'] : null;
        $status = strtoupper((string) ($filters['status'] ?? ''));
        $dateFrom = (string) ($filters['date_from'] ?? $filters['from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? $filters['to'] ?? '');

        if ($actor !== null) {
            $scope = $this->scope->scopeFor($actor);
            $scopeType = $scope['type'] ?? 'none';

            if ($scopeType === 'district') {
                if ($centreId !== null) {
                    if (!$this->scope->canAccessCentre($actor, $centreId)) {
                        throw new AuthorizationException('You do not have access to this centre');
                    }
                    $where[] = 's.centre_id = ?';
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
                    $where[] = 's.centre_id = ?';
                    $params[] = $centreId;
                } elseif (!empty($scope['centre_ids'])) {
                    $placeholders = implode(',', array_fill(0, count($scope['centre_ids']), '?'));
                    $where[] = 's.centre_id IN (' . $placeholders . ')';
                    $params = array_merge($params, $scope['centre_ids']);
                } else {
                    $where[] = '1 = 0';
                }
            } elseif ($scopeType === 'all') {
                if ($centreId !== null) {
                    $where[] = 's.centre_id = ?';
                    $params[] = $centreId;
                }
            } else {
                $where[] = '1 = 0';
            }
        } elseif ($centreId !== null) {
            $where[] = 's.centre_id = ?';
            $params[] = $centreId;
        }

        if ($status !== '') {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if ($dateFrom !== '') {
            $where[] = 's.date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 's.date <= ?';
            $params[] = $dateTo;
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM slots s
             INNER JOIN procurement_centres c ON c.id = s.centre_id
             WHERE {$whereSql}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT s.*, c.name AS centre_name, c.code AS centre_code
             FROM slots s
             INNER JOIN procurement_centres c ON c.id = s.centre_id
             WHERE {$whereSql}
             ORDER BY s.date DESC, s.start_time ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = array_map(fn($row) => $this->details($row), $rows);

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

    public function details(array $slot, bool $public = false): array
    {
        $booked = $this->slots->bookedCount((int) $slot['id']);
        $remaining = max(0, (int) $slot['capacity'] - $booked);

        $result = [
            'id' => (int) $slot['id'],
            'centre_id' => (int) $slot['centre_id'],
            'date' => $slot['date'],
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'capacity' => (int) $slot['capacity'],
            'booked' => $booked,
            'remaining' => $remaining,
            'status' => $slot['status'],
        ];

        if (!$public) {
            $result['created_by'] = $slot['created_by'] !== null ? (int) $slot['created_by'] : null;
            $result['created_at'] = $slot['created_at'];
            $result['updated_at'] = $slot['updated_at'];
        }

        if (isset($slot['centre_name'])) {
            $result['centre'] = [
                'id' => (int) $slot['centre_id'],
                'name' => $slot['centre_name'],
                'code' => $slot['centre_code'] ?? null,
            ];
        }

        return $result;
    }

    private function assertCancellable(array $actor, array $slot): void
    {
        $this->scope->assertCentreScope($actor, (int) $slot['centre_id']);

        if ((string) $slot['status'] !== Slot::STATUS_ACTIVE) {
            throw new ConflictException(
                'SLOT_NOT_CANCELLABLE',
                'Only slots in AVAILABLE state can be cancelled'
            );
        }

        if ($this->slots->bookedCount((int) $slot['id']) > 0) {
            throw new ConflictException(
                'SLOT_HAS_BOOKINGS',
                'Cannot cancel a slot that has bookings'
            );
        }
    }

    private function requireActiveCentre(int $centreId): array
    {
        $centre = $this->centres->find($centreId);
        if ($centre === null) {
            throw new NotFoundException('CENTRE_NOT_FOUND', 'Procurement centre not found');
        }
        if ((string) $centre['status'] !== ProcurementCentre::STATUS_ACTIVE) {
            throw new BadRequestException(
                'CENTRE_INACTIVE',
                'Slots can only be created for active centres'
            );
        }
        return $centre;
    }

    private function assertFutureDate(string $date): void
    {
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            throw new BadRequestException('DATE_OUT_OF_RANGE', 'Slot date cannot be in the past');
        }
    }

    private function assertTimeRange(array $centre, string $date, string $startTime, string $endTime): void
    {
        if (strtotime($endTime) <= strtotime($startTime)) {
            throw new BadRequestException('TIME_ERROR', 'start_time must be before end_time');
        }

        $openStart = $centre['working_hours_start'] ?? null;
        $openEnd = $centre['working_hours_end'] ?? null;
        if ($openStart === null || $openEnd === null) {
            return;
        }

        $inMinutes = fn($t) => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
        $openStartM = $inMinutes($openStart);
        $openEndM = $inMinutes($openEnd);
        $startM = $inMinutes($startTime);
        $endM = $inMinutes($endTime);

        if ($startM < $openStartM || $endM > $openEndM) {
            throw new BadRequestException(
                'OUTSIDE_CENTRE_HOURS',
                'Slot time is outside the centre working hours (' . $openStart . ' - ' . $openEnd . ')'
            );
        }

        $dayName = strtoupper(date('D', strtotime($date)));
        $workingDays = array_map('strtoupper', array_map('trim', explode(',', (string) ($centre['working_days'] ?? ''))));
        if (!empty($workingDays) && !in_array($dayName, $workingDays, true)) {
            throw new BadRequestException('CENTRE_CLOSED_DAY', 'The centre is closed on ' . $dayName);
        }
    }

    private function assertGranularity(string $time): void
    {
        $minutes = (int) substr($time, 3, 2);
        if ($minutes % 15 !== 0) {
            throw new BadRequestException(
                'GRANULARITY_ERROR',
                'Slot times must be on 15-minute granularity'
            );
        }
    }
}
