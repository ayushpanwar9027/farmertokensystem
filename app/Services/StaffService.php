<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\CentreStaff;
use App\Models\Role;
use App\Models\User;
use App\Validators\StaffValidator;

class StaffService
{
    private const STAFF_ROLES = ['SUPER_ADMIN', 'DISTRICT_ADMIN', 'CENTRE_MANAGER', 'CENTRE_OPERATOR'];

    private StaffValidator $validator;
    private RbacService $rbac;
    private ScopeService $scope;
    private AuditService $audit;
    private User $users;
    private Role $roles;
    private CentreStaff $centreStaff;

    public function __construct()
    {
        $this->validator = new StaffValidator();
        $this->rbac = new RbacService();
        $this->scope = new ScopeService();
        $this->audit = new AuditService();
        $this->users = new User();
        $this->roles = new Role();
        $this->centreStaff = new CentreStaff();
    }

    public function create(array $actor, array $data): array
    {
        $data = $this->validator->create($data);

        $name = trim((string) $data['name']);
        $mobile = trim((string) $data['mobile']);
        $role = $this->validator->resolveRole((string) $data['role']);
        $roleName = (string) $role['name'];

        $this->assertRoleAllowed($actor, $roleName, (int) $role['level']);

        $email = isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null;
        $username = isset($data['username']) && trim((string) $data['username']) !== '' ? trim((string) $data['username']) : null;

        if ($this->users->byMobile($mobile) !== null) {
            throw new ConflictException('DUPLICATE_STAFF', 'A user with this mobile already exists');
        }
        if ($username !== null && $this->users->byUsername($username) !== null) {
            throw new ValidationException(['username' => ['This username is already taken']]);
        }
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException(['email' => ['A valid email address is required']]);
            }
            if ($this->users->byEmail($email) !== null) {
                throw new ValidationException(['email' => ['This email is already taken']]);
            }
        }

        $centre = null;
        if (in_array($roleName, ['CENTRE_MANAGER', 'CENTRE_OPERATOR'], true)) {
            if (empty($data['centre_id'])) {
                throw new ValidationException(['centre_id' => ['Centre assignment is required for ' . $roleName]]);
            }
            $centre = $this->resolveCentre((int) $data['centre_id']);
            $this->scope->assertCentreScope($actor, (int) $centre['id']);
        } elseif (in_array($roleName, ['DISTRICT_ADMIN', 'SUPER_ADMIN'], true)) {
            if (!empty($data['centre_id'])) {
                $centre = $this->resolveCentre((int) $data['centre_id']);
                $this->scope->assertCentreScope($actor, (int) $centre['id']);
            }
        }

        $username = $username ?? $this->generateUsername($roleName, $mobile);

        $status = strtoupper((string) ($data['status'] ?? 'ACTIVE'));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new BadRequestException('STAFF_STATUS_INVALID', 'Staff status must be ACTIVE or INACTIVE');
        }

        Database::beginTransaction();
        try {
            $userId = $this->users->insert([
                'name' => $name,
                'mobile' => $mobile,
                'email' => $email,
                'username' => $username,
                'password_hash' => '!' . bin2hex(random_bytes(32)),
                'password_set_at' => null,
                'role_id' => (int) $role['id'],
                'status' => $status,
                'verification_status' => 'APPROVED',
                'is_super_admin' => $roleName === 'SUPER_ADMIN' ? 1 : 0,
                'created_by' => (int) $actor['id'],
            ]);

            if ($centre !== null) {
                $this->centreStaff->assign(
                    (int) $centre['id'],
                    $userId,
                    $roleName === 'DISTRICT_ADMIN' ? 'CENTRE_MANAGER' : $roleName,
                    $roleName === 'CENTRE_MANAGER'
                );
            }

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'STAFF_CREATED',
                'module' => 'STAFF',
                'entity_type' => 'user',
                'entity_id' => $userId,
                'new_value' => [
                    'name' => $name,
                    'mobile' => $mobile,
                    'username' => $username,
                    'role' => $roleName,
                    'centre_id' => $centre['id'] ?? null,
                    'status' => $status,
                ],
                'reason' => 'admin_create_staff',
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $this->rbac->clearCache($userId);

        $staff = $this->users->find($userId);
        $staff['role'] = $roleName;

        return $this->details($staff);
    }

    public function update(array $actor, array $staff, array $data): array
    {
        $data = $this->validator->update($data);
        $this->assertTargetWithinScope($actor, $staff);

        $updates = [];
        if (array_key_exists('name', $data) && $data['name'] !== null && trim((string) $data['name']) !== '') {
            $updates['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('mobile', $data) && $data['mobile'] !== null && trim((string) $data['mobile']) !== '') {
            $mobile = trim((string) $data['mobile']);
            $existing = $this->users->byMobile($mobile);
            if ($existing !== null && (int) $existing['id'] !== (int) $staff['id']) {
                throw new ConflictException('DUPLICATE_STAFF', 'A user with this mobile already exists');
            }
            $updates['mobile'] = $mobile;
        }
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $email = trim((string) $data['email']);
            if ($email === '') {
                $updates['email'] = null;
            } else {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new ValidationException(['email' => ['A valid email address is required']]);
                }
                $existing = $this->users->byEmail($email);
                if ($existing !== null && (int) $existing['id'] !== (int) $staff['id']) {
                    throw new ValidationException(['email' => ['This email is already taken']]);
                }
                $updates['email'] = $email;
            }
        }
        if (array_key_exists('username', $data) && $data['username'] !== null) {
            $username = trim((string) $data['username']);
            if ($username === '') {
                throw new ValidationException(['username' => ['Username may not be empty']]);
            }
            $existing = $this->users->byUsername($username);
            if ($existing !== null && (int) $existing['id'] !== (int) $staff['id']) {
                throw new ValidationException(['username' => ['This username is already taken']]);
            }
            $updates['username'] = $username;
        }

        if (empty($updates)) {
            throw new ValidationException(['data' => ['No valid fields were provided to update']]);
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');
        Database::update('users', $updates, 'id = ?', [(int) $staff['id']]);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'STAFF_UPDATED',
            'module' => 'STAFF',
            'entity_type' => 'user',
            'entity_id' => (int) $staff['id'],
            'old_value' => array_intersect_key($staff, $updates),
            'new_value' => $updates,
            'reason' => 'admin_update_staff',
        ]);

        $this->rbac->clearCache((int) $staff['id']);

        return $this->details(
            $this->users->withRole($this->users->find((int) $staff['id']))
        );
    }

    public function changeStatus(array $actor, array $staff, string $status): array
    {
        $status = strtoupper(trim($status));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new BadRequestException('STAFF_STATUS_INVALID', 'Staff status must be ACTIVE or INACTIVE');
        }

        $this->assertTargetWithinScope($actor, $staff);

        if ($status === 'INACTIVE' && !empty($staff['is_super_admin'])) {
            throw new BadRequestException('ROLE_NOT_ALLOWED', 'A Super Admin account cannot be deactivated');
        }

        Database::update(
            'users',
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [(int) $staff['id']]
        );

        if ($status === 'INACTIVE') {
            (new SessionService())->revokeAllForUser((int) $staff['id'], (int) $actor['id'], 'staff_deactivated');
        }

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'STAFF_STATUS_CHANGED',
            'module' => 'STAFF',
            'entity_type' => 'user',
            'entity_id' => (int) $staff['id'],
            'old_value' => ['status' => $staff['status']],
            'new_value' => ['status' => $status],
            'reason' => 'admin_change_staff_status',
        ]);

        $this->rbac->clearCache((int) $staff['id']);

        return $this->details(
            $this->users->withRole($this->users->find((int) $staff['id']))
        );
    }

    public function assignCentre(array $actor, array $staff, int $centreId): array
    {
        $this->assertTargetWithinScope($actor, $staff);

        $staffRole = (string) ($staff['role'] ?? '');
        if ($staffRole === '') {
            $staffRole = $this->users->roleNameFor((int) $staff['id']);
            $staff['role'] = $staffRole;
        }

        if ($staffRole === 'SUPER_ADMIN') {
            throw new BadRequestException('ROLE_NOT_ALLOWED', 'A SUPER_ADMIN is not assigned to a centre');
        }
        if ($staffRole === 'FARMER') {
            throw new BadRequestException('ROLE_NOT_ALLOWED', 'Farmers cannot be assigned to a centre');
        }

        $centre = $this->resolveCentre($centreId);
        $this->scope->assertCentreScope($actor, $centreId);

        if ($staffRole === 'DISTRICT_ADMIN') {
            if (!in_array((int) $centre['district_id'], $this->scope->districtIdsFor($staff), true)) {
                throw new AuthorizationException('You do not have access to the selected centre');
            }
            $this->centreStaff->reassign((int) $staff['id'], $centreId, 'CENTRE_MANAGER');
        } else {
            $this->centreStaff->reassign((int) $staff['id'], $centreId, $staffRole);
        }

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'STAFF_CENTRE_ASSIGNED',
            'module' => 'STAFF',
            'entity_type' => 'user',
            'entity_id' => (int) $staff['id'],
            'new_value' => [
                'centre_id' => $centreId,
                'centre_code' => $centre['code'],
                'role' => $staffRole,
            ],
            'reason' => 'admin_assign_staff_centre',
        ]);

        return $this->details(
            $this->users->withRole($this->users->find((int) $staff['id']))
        );
    }

    public function show(array $actor, array $staff): array
    {
        $this->assertTargetWithinScope($actor, $staff);
        return $this->details(
            $this->users->withRole($this->users->find((int) $staff['id']))
        );
    }

    public function list(array $actor, array $filters, int $page, int $perPage): array
    {
        $maxLevel = $this->currentLevel($actor);
        $excludeSuperAdminGrants = !$this->rbac->isSuperAdmin($actor);

        $where = ['u.deleted_at IS NULL', 'r.level < ?'];
        $params = [$maxLevel];

        $scope = $this->scope->scopeFor($actor);

        $q = trim((string) ($filters['q'] ?? ''));
        $status = strtoupper((string) ($filters['status'] ?? ''));
        $roleName = strtoupper((string) ($filters['role'] ?? ''));
        $centreId = isset($filters['centre_id']) && $filters['centre_id'] !== '' ? (int) $filters['centre_id'] : null;
        $districtId = isset($filters['district_id']) && $filters['district_id'] !== '' ? (int) $filters['district_id'] : null;

        if ($scope['type'] === 'district') {
            $districtIds = array_filter($scope['district_ids']);
            if (empty($districtIds)) {
                $where[] = '1 = 0';
                $scopeIds = [];
            } else {
                $scopeIds = $districtIds;
            }
        } elseif ($scope['type'] === 'centre') {
            $scopeIds = array_filter($scope['centre_ids']);
            $where[] = 'EXISTS (
                SELECT 1 FROM centre_staff cs WHERE cs.user_id = u.id AND cs.deleted_at IS NULL
                AND cs.centre_id IN (' . implode(',', array_fill(0, max(1, count($scopeIds)), '?')) . ')
            )';
            $params = array_merge($params, array_values($scopeIds));
            $scopeIds = [];
        } else {
            $scopeIds = [];
        }

        if ($scope['type'] === 'district' && !empty($scopeIds)) {
            $placeholders = implode(',', array_fill(0, count($scopeIds), '?'));
            $where[] = "(u.created_by = ? OR EXISTS (
                SELECT 1 FROM centre_staff cs
                INNER JOIN procurement_centres c ON c.id = cs.centre_id
                WHERE cs.user_id = u.id AND cs.deleted_at IS NULL AND c.deleted_at IS NULL
                AND c.district_id IN ({$placeholders})
            ))";
            $params[] = (int) $actor['id'];
            $params = array_merge($params, array_values($scopeIds));
        }

        if ($excludeSuperAdminGrants) {
            $where[] = 'u.is_super_admin = 0';
        }

        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.mobile LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($status !== '') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($roleName !== '') {
            $where[] = 'r.name = ?';
            $params[] = $roleName;
        }
        if ($districtId !== null) {
            if ($this->rbac->isSuperAdmin($actor)) {
                $where[] = 'EXISTS (
                    SELECT 1 FROM centre_staff cs
                    INNER JOIN procurement_centres c ON c.id = cs.centre_id
                    WHERE cs.user_id = u.id AND cs.deleted_at IS NULL AND c.deleted_at IS NULL
                    AND c.district_id = ?
                )';
                $params[] = $districtId;
            } elseif ($scope['type'] === 'all') {
                $where[] = 'EXISTS (
                    SELECT 1 FROM centre_staff cs
                    INNER JOIN procurement_centres c ON c.id = cs.centre_id
                    WHERE cs.user_id = u.id AND cs.deleted_at IS NULL AND c.deleted_at IS NULL
                    AND c.district_id = ?
                )';
                $params[] = $districtId;
            } else {
                $allowed = $this->scope->districtIdsFor($actor);
                if (!in_array($districtId, $allowed, true)) {
                    throw new AuthorizationException('You do not have access to this district');
                }
            }
        }
        if ($centreId !== null) {
            if ($this->rbac->isSuperAdmin($actor) || $scope['type'] === 'all') {
                $where[] = 'EXISTS (
                    SELECT 1 FROM centre_staff cs WHERE cs.user_id = u.id AND cs.deleted_at IS NULL AND cs.centre_id = ?
                )';
                $params[] = $centreId;
            } elseif (!in_array($centreId, $this->scope->centreIdsFor($actor), true)) {
                throw new AuthorizationException('You do not have access to this centre');
            }
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE {$whereSql}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT u.id, u.name, u.mobile, u.email, u.username, u.role_id, r.name AS role_name,
                    r.level AS role_level, u.is_super_admin, u.status, u.verification_status,
                    u.created_by, u.last_login_at, u.password_set_at, u.created_at
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE {$whereSql}
             ORDER BY r.level DESC, u.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = [];
        foreach ($rows as $row) {
            $row['role'] = $row['role_name'];
            $data[] = $this->summary($row);
        }

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

    public function details(array $staff): array
    {
        $centreAssignment = $this->centreStaff->assignmentForUser((int) $staff['id']);
        $roleName = (string) ($staff['role'] ?? '');

        return [
            'id' => (int) $staff['id'],
            'name' => $staff['name'],
            'mobile' => $staff['mobile'],
            'email' => $staff['email'],
            'username' => $staff['username'],
            'role' => [
                'id' => (int) ($staff['role_id'] ?? 0),
                'name' => $roleName,
                'level' => (int) ($staff['role_level'] ?? $this->roles->level((int) ($staff['role_id'] ?? 0))),
            ],
            'is_super_admin' => (int) ($staff['is_super_admin'] ?? 0),
            'status' => $staff['status'],
            'verification_status' => $staff['verification_status'] ?? 'APPROVED',
            'password_set' => !empty($staff['password_set_at']),
            'has_centre_assignment' => $centreAssignment !== null,
            'centre' => $centreAssignment !== null ? $this->centreSummary((int) $centreAssignment['centre_id'], $centreAssignment) : null,
            'permissions' => $this->rbac->effectivePermissions((int) ($staff['id'] ?? 0)),
            'created_by' => $staff['created_by'] !== null ? (int) $staff['created_by'] : null,
            'last_login_at' => $staff['last_login_at'] ?? null,
            'created_at' => $staff['created_at'],
        ];
    }

    public function assertTargetWithinScope(array $actor, array $target): void
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return;
        }

        $scopeInfo = $this->scope->scopeFor($actor);
        if ($scopeInfo['type'] === 'all') {
            return;
        }

        if ((int) ($target['created_by'] ?? 0) === (int) $actor['id']) {
            return;
        }

        $targetRole = $this->roles->find((int) ($target['role_id'] ?? 0));

        if ($scopeInfo['type'] === 'district') {
            $targetDistricts = $this->targetDistrictIds((int) $target['id']);
            foreach ($targetDistricts as $districtId) {
                if (in_array($districtId, $scopeInfo['district_ids'], true)) {
                    return;
                }
            }
            throw new AuthorizationException('This staff member is outside your district scope');
        }

        if ($scopeInfo['type'] === 'centre') {
            $targetCentres = $this->targetCentreIds((int) $target['id']);
            foreach ($targetCentres as $centreId) {
                if (in_array($centreId, $scopeInfo['centre_ids'], true)) {
                    return;
                }
            }
            throw new AuthorizationException('This staff member is outside your centre scope');
        }

        throw new AuthorizationException('You cannot manage this user');
    }

    private function assertRoleAllowed(array $actor, string $roleName, int $roleLevel): void
    {
        if (!in_array($roleName, self::STAFF_ROLES, true)) {
            throw new ValidationException(['role' => ['The selected role is not an assignable staff role']]);
        }

        if ($roleName === 'SUPER_ADMIN' && !$this->rbac->isSuperAdmin($actor)) {
            throw new BadRequestException('ROLE_NOT_ALLOWED', 'Only a Super Admin can create or assign the SUPER_ADMIN role');
        }

        if ($roleLevel >= $this->currentLevel($actor) && !$this->rbac->isSuperAdmin($actor)) {
            throw new BadRequestException('ROLE_NOT_ALLOWED', 'You cannot assign a role at or above your own level');
        }
    }

    private function resolveCentre(int $centreId): array
    {
        $row = Database::selectOne(
            "SELECT * FROM procurement_centres WHERE id = ? AND deleted_at IS NULL",
            [$centreId]
        );
        if ($row === null) {
            throw new NotFoundException('CENTRE_NOT_FOUND', 'Procurement centre not found');
        }
        return $row;
    }

    public function find(int $userId): array
    {
        $staff = $this->users->find($userId);
        if ($staff === null) {
            throw new NotFoundException('STAFF_NOT_FOUND', 'Staff user not found');
        }
        return $this->users->withRole($staff);
    }

    private function targetDistrictIds(int $userId): array
    {
        $rows = Database::select(
            "SELECT DISTINCT c.district_id
             FROM centre_staff cs
             INNER JOIN procurement_centres c ON c.id = cs.centre_id
             WHERE cs.user_id = ? AND cs.deleted_at IS NULL AND c.deleted_at IS NULL",
            [$userId]
        );
        $ids = array_map(fn($r) => (int) $r['district_id'], $rows);
        if (empty($ids)) {
            $row = Database::selectOne(
                "SELECT district_id FROM farmers WHERE user_id = ? AND deleted_at IS NULL LIMIT 1",
                [$userId]
            );
            $ids = $row !== null ? [(int) $row['district_id']] : [];
        }
        return array_values(array_unique($ids));
    }

    private function targetCentreIds(int $userId): array
    {
        $rows = Database::select(
            "SELECT DISTINCT cs.centre_id
             FROM centre_staff cs
             WHERE cs.user_id = ? AND cs.deleted_at IS NULL",
            [$userId]
        );
        return array_map(fn($r) => (int) $r['centre_id'], $rows);
    }

    private function currentLevel(array $user): int
    {
        return $this->roles->level((int) ($user['role_id'] ?? 0));
    }

    private function generateUsername(string $roleName, string $mobile): string
    {
        $prefix = strtolower(str_replace('_', '', $roleName));
        return $prefix . substr($mobile, -4);
    }

    private function centreSummary(int $centreId, array $assignment): ?array
    {
        $row = Database::selectOne(
            "SELECT id, name, code, district_id, status FROM procurement_centres WHERE id = ? AND deleted_at IS NULL",
            [$centreId]
        );
        if ($row === null) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'district_id' => (int) $row['district_id'],
            'status' => $row['status'],
            'assignment_role' => $assignment['role'] ?? null,
            'is_primary' => (int) ($assignment['is_primary'] ?? 0) === 1,
        ];
    }

    private function summary(array $staff): array
    {
        $d = $this->details($staff);
        unset($d['permissions']);
        return $d;
    }
}