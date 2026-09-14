<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\District;
use App\Models\ProcurementCentre;
use App\Validators\CentreValidator;

class CentreService
{
    private CentreValidator $validator;
    private ScopeService $scope;
    private RbacService $rbac;
    private AuditService $audit;
    private ProcurementCentre $centres;

    public function __construct()
    {
        $this->validator = new CentreValidator();
        $this->scope = new ScopeService();
        $this->rbac = new RbacService();
        $this->audit = new AuditService();
        $this->centres = new ProcurementCentre();
    }

    public function create(array $actor, array $data): array
    {
        $data = $this->validator->create($data);

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = $this->suggestCode((string) ($data['name'] ?? ''));
        }

        if ($this->centres->codeExists($code)) {
            throw new ConflictException('CENTRE_CODE_EXISTS', 'A centre with this code already exists');
        }

        $districtId = (int) $data['district_id'];
        $this->assertDistrictExists($districtId);
        $this->scope->assertUserScope($actor, $districtId);

        $status = strtoupper((string) ($data['status'] ?? ProcurementCentre::STATUS_ACTIVE));
        if (!in_array($status, ProcurementCentre::VALID_STATUSES, true)) {
            throw new ValidationException(['status' => ['The status must be one of: ACTIVE, INACTIVE, CLOSED']]);
        }

        $row = [
            'name' => trim((string) $data['name']),
            'code' => $code,
            'district_id' => $districtId,
            'address' => trim((string) $data['address']),
            'contact_phone' => isset($data['contact_phone']) && trim((string) $data['contact_phone']) !== '' ? trim((string) $data['contact_phone']) : null,
            'contact_email' => isset($data['contact_email']) && trim((string) $data['contact_email']) !== '' ? trim((string) $data['contact_email']) : null,
            'working_hours_start' => $data['working_hours_start'] ?? '09:00:00',
            'working_hours_end' => $data['working_hours_end'] ?? '17:00:00',
            'working_days' => isset($data['working_days']) && trim((string) $data['working_days']) !== '' ? strtoupper(trim((string) $data['working_days'])) : 'MON,TUE,WED,THU,FRI',
            'daily_capacity' => (int) ($data['daily_capacity'] ?? 100),
            'slot_duration_minutes' => (int) ($data['slot_duration_minutes'] ?? 30),
            'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? $data['latitude'] : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? $data['longitude'] : null,
            'manager_user_id' => null,
            'status' => $status,
        ];

        Database::beginTransaction();
        try {
            $centreId = $this->centres->insert($row);

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'CENTRE_CREATED',
                'module' => 'CENTRES',
                'entity_type' => 'procurement_centre',
                'entity_id' => $centreId,
                'new_value' => $row,
                'reason' => 'admin_create_centre',
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $centre = $this->centres->find($centreId);
        return $this->details($centre);
    }

    public function update(array $actor, array $centre, array $data): array
    {
        $data = $this->validator->update($data);

        $this->scope->assertCentreScope($actor, (int) $centre['id']);

        $updates = [];
        foreach (['name', 'address', 'contact_phone', 'contact_email', 'working_days'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $updates[$field] = trim((string) $data[$field]);
            }
        }
        foreach (['working_hours_start', 'working_hours_end'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $updates[$field] = (string) $data[$field];
            }
        }
        foreach (['daily_capacity', 'slot_duration_minutes', 'latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('district_id', $data) && $data['district_id'] !== null && $data['district_id'] !== '') {
            $districtId = (int) $data['district_id'];
            $this->assertDistrictExists($districtId);
            $this->scope->assertUserScope($actor, $districtId);
            $this->scope->assertCentreScope($actor, (int) $centre['id']);
            $updates['district_id'] = $districtId;
        }

        if (array_key_exists('code', $data) && $data['code'] !== null && trim((string) $data['code']) !== '') {
            $code = strtoupper(trim((string) $data['code']));
            if ($this->centres->codeExists($code, (int) $centre['id'])) {
                throw new ConflictException('CENTRE_CODE_EXISTS', 'A centre with this code already exists');
            }
            $updates['code'] = $code;
        }

        if (empty($updates)) {
            throw new ValidationException(['data' => ['No valid fields were provided to update']]);
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');

        Database::beginTransaction();
        try {
            Database::update('procurement_centres', $updates, 'id = ?', [(int) $centre['id']]);

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'CENTRE_UPDATED',
                'module' => 'CENTRES',
                'entity_type' => 'procurement_centre',
                'entity_id' => (int) $centre['id'],
                'old_value' => array_intersect_key($centre, $updates),
                'new_value' => $updates,
                'reason' => 'admin_update_centre',
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return $this->details($this->centres->find((int) $centre['id']));
    }

    public function changeStatus(array $actor, array $centre, string $status): array
    {
        $status = strtoupper(trim($status));
        if (!in_array($status, ProcurementCentre::VALID_STATUSES, true)) {
            throw new ValidationException(['status' => ['The status must be one of: ACTIVE, INACTIVE, CLOSED']]);
        }

        $this->scope->assertCentreScope($actor, (int) $centre['id']);

        if (in_array($status, [ProcurementCentre::STATUS_INACTIVE, ProcurementCentre::STATUS_CLOSED], true)) {
            if ($this->centres->hasFutureBookings((int) $centre['id'])) {
                throw new ConflictException(
                    'CENTRE_HAS_BOOKINGS',
                    'Cannot deactivate a centre that has active future bookings; cancel or complete them first'
                );
            }
        }

        $updates = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        Database::update('procurement_centres', $updates, 'id = ?', [(int) $centre['id']]);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'CENTRE_STATUS_CHANGED',
            'module' => 'CENTRES',
            'entity_type' => 'procurement_centre',
            'entity_id' => (int) $centre['id'],
            'old_value' => ['status' => $centre['status']],
            'new_value' => ['status' => $status],
            'reason' => 'admin_change_centre_status',
        ]);

        return $this->details($this->centres->find((int) $centre['id']));
    }

    public function show(?array $actor, int $centreId): array
    {
        $centre = $this->centres->find($centreId);
        if ($centre === null) {
            throw new NotFoundException('CENTRE_NOT_FOUND', 'Procurement centre not found');
        }
        if ($actor !== null) {
            $this->scope->assertCentreScope($actor, $centreId);
        }
        return $this->details($centre);
    }

    public function list(?array $actor, array $filters, int $page, int $perPage): array
    {
        $where = ['pc.deleted_at IS NULL'];
        $params = [];

        $status = strtoupper((string) ($filters['status'] ?? ''));
        $districtId = isset($filters['district_id']) && $filters['district_id'] !== '' ? (int) $filters['district_id'] : null;
        $q = trim((string) ($filters['q'] ?? ''));

        $scopeForAdmin = $actor !== null && $this->staffScopeActive($actor);

        if ($scopeForAdmin) {
            $scope = $this->scope->scopeFor($actor);
            if ($scope['type'] === 'district') {
                if ($districtId !== null) {
                    if (!in_array($districtId, $scope['district_ids'], true)) {
                        throw new AuthorizationException('You do not have access to this district');
                    }
                    $where[] = 'pc.district_id = ?';
                    $params[] = $districtId;
                } elseif (!empty($scope['district_ids'])) {
                    $placeholders = implode(',', array_fill(0, count($scope['district_ids']), '?'));
                    $where[] = 'pc.district_id IN (' . $placeholders . ')';
                    $params = array_merge($params, $scope['district_ids']);
                } else {
                    $where[] = '1 = 0';
                }
            } elseif ($scope['type'] === 'centre') {
                if (!empty($scope['centre_ids'])) {
                    if ($districtId !== null) {
                        $districtIds = $this->scope->districtIdsFor($actor);
                        if (!in_array($districtId, $districtIds, true)) {
                            throw new AuthorizationException('You do not have access to this district');
                        }
                        $where[] = 'pc.district_id = ?';
                        $params[] = $districtId;
                    }
                    $placeholders = implode(',', array_fill(0, count($scope['centre_ids']), '?'));
                    $where[] = 'pc.id IN (' . $placeholders . ')';
                    $params = array_merge($params, $scope['centre_ids']);
                } else {
                    $where[] = '1 = 0';
                }
                if ($status !== '') {
                    $where[] = 'pc.status = ?';
                    $params[] = $status;
                }
            } elseif ($scope['type'] === 'all') {
                if ($districtId !== null) {
                    $where[] = 'pc.district_id = ?';
                    $params[] = $districtId;
                }
                if ($status !== '') {
                    $where[] = 'pc.status = ?';
                    $params[] = $status;
                }
            } else {
                $where[] = '1 = 0';
            }
        } else {
            if ($districtId !== null) {
                $where[] = 'pc.district_id = ?';
                $params[] = $districtId;
            }
            $where[] = "pc.status = 'ACTIVE'";
        }

        if ($q !== '') {
            $where[] = '(pc.name LIKE ? OR pc.code LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM procurement_centres pc WHERE {$whereSql}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT pc.id, pc.name, pc.code, pc.district_id, d.name AS district_name,
                    pc.status, pc.working_hours_start, pc.working_hours_end, pc.working_days,
                    pc.daily_capacity, pc.slot_duration_minutes, pc.contact_phone, pc.contact_email,
                    pc.address, pc.latitude, pc.longitude, pc.created_at, pc.updated_at
             FROM procurement_centres pc
             INNER JOIN districts d ON d.id = pc.district_id
             WHERE {$whereSql}
             ORDER BY pc.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = array_map(function (array $row) use ($actor) {
            return $this->details($row, $actor);
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

    public function details(array $centre, ?array $viewer = null): array
    {
        $district = Database::selectOne(
            "SELECT id, name, code FROM districts WHERE id = ?",
            [(int) $centre['district_id']]
        );

        $manager = null;
        $operators = [];
        $staff = [];

        $showInternal = $viewer !== null
            && ($this->rbac->isSuperAdmin($viewer)
                || $this->rbac->can($viewer, 'centres.manage')
                || $this->rbac->can($viewer, 'centres.status.manage'));

        if ($showInternal) {
            $manager = $this->centres->manager((int) $centre['id']);
            $operators = $this->centres->operators((int) $centre['id']);
            $staff = $this->centres->staff((int) $centre['id']);
        }

        return [
            'id' => (int) $centre['id'],
            'name' => $centre['name'],
            'code' => $centre['code'],
            'district' => $district !== null ? [
                'id' => (int) $district['id'],
                'name' => $district['name'],
                'code' => $district['code'],
            ] : null,
            'district_id' => (int) $centre['district_id'],
            'address' => $centre['address'],
            'contact_phone' => $centre['contact_phone'],
            'contact_email' => $centre['contact_email'],
            'working_hours' => [
                'start' => $centre['working_hours_start'],
                'end' => $centre['working_hours_end'],
            ],
            'working_days' => $centre['working_days'],
            'daily_capacity' => (int) $centre['daily_capacity'],
            'slot_duration_minutes' => (int) $centre['slot_duration_minutes'],
            'location' => [
                'latitude' => $centre['latitude'] !== null ? (float) $centre['latitude'] : null,
                'longitude' => $centre['longitude'] !== null ? (float) $centre['longitude'] : null,
            ],
            'status' => $centre['status'],
            'manager' => $manager,
            'operators' => $operators,
            'staff' => $staff,
            'created_at' => $centre['created_at'],
            'updated_at' => $centre['updated_at'],
        ];
    }

    public function suggestCode(string $name): string
    {
        $clean = preg_replace('/[^A-Z0-9]/i', '', (string) $name);
        $prefix = strtoupper(substr((string) $clean, 0, 3));
        if (strlen($prefix) < 2 || !preg_match('/[A-Z]/', $prefix)) {
            $prefix = 'CTR';
        }
        $code = $prefix . '01';
        $n = 2;
        while ($this->centres->codeExists($code)) {
            $code = $prefix . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $n++;
        }
        return $code;
    }

    private function staffScopeActive(array $actor): bool
    {
        $role = strtoupper((string) ($actor['role'] ?? ''));
        return in_array($role, ['SUPER_ADMIN', 'DISTRICT_ADMIN', 'CENTRE_MANAGER', 'CENTRE_OPERATOR'], true);
    }

    private function assertDistrictExists(int $districtId): void
    {
        if (!(new District())->isActive($districtId)) {
            throw new NotFoundException('DISTRICT_NOT_FOUND', 'District not found or inactive');
        }
    }
}