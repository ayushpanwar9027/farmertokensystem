<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\AuditService;
use App\Services\RbacService;
use App\Services\ScopeService;

class RolePermissionController
{
    private RbacService $rbac;
    private ScopeService $scope;
    private AuditService $audit;

    public function __construct()
    {
        $this->rbac = new RbacService();
        $this->scope = new ScopeService();
        $this->audit = new AuditService();
    }

    public function index(Request $request): void
    {
        $user = $this->requireActor($request);
        $this->rbac->assertCan($user, 'manage_staff', 'You do not have permission to view staff');

        $actorLevel = $this->currentLevel($user);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $where = ["u.deleted_at IS NULL", "r.level > 10"];
        $params = [];

        if (!$this->rbac->isSuperAdmin($user)) {
            $scopeInfo = $this->scope->scopeFor($user);
            if ($scopeInfo['type'] === 'district') {
                $districtIds = array_filter($scopeInfo['district_ids']);
                if (empty($districtIds)) {
                    $districtIds = [$this->fallbackDistrictId($user)];
                }
                $placeholders = implode(',', array_fill(0, count($districtIds), '?'));
                $where[] = "(u.created_by = ? OR EXISTS (
                    SELECT 1 FROM centre_staff cs2
                    INNER JOIN procurement_centres c2 ON c2.id = cs2.centre_id
                    WHERE cs2.user_id = u.id AND cs2.deleted_at IS NULL AND c2.deleted_at IS NULL
                    AND c2.district_id IN ({$placeholders})
                ))";
                $params[] = (int) $user['id'];
                foreach ($districtIds as $districtId) {
                    $params[] = (int) $districtId;
                }
            } elseif ($scopeInfo['type'] === 'centre') {
                $centreIds = array_filter($scopeInfo['centre_ids']);
                if (empty($centreIds)) {
                    throw new AuthorizationException('You are not assigned to any centre');
                }
                $placeholders = implode(',', array_fill(0, count($centreIds), '?'));
                $where[] = "EXISTS (
                    SELECT 1 FROM centre_staff cs3
                    WHERE cs3.user_id = u.id AND cs3.deleted_at IS NULL
                    AND cs3.centre_id IN ({$placeholders})
                )";
                foreach ($centreIds as $centreId) {
                    $params[] = (int) $centreId;
                }
            } else {
                throw new AuthorizationException('You do not have permission to view staff');
            }
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE {$whereSql}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT u.id, u.name, u.mobile, u.email, u.username, u.role_id, r.name AS role_name,
                    r.level AS role_level, u.is_super_admin, u.status, u.created_by, u.created_at
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE {$whereSql}
             ORDER BY r.level DESC, u.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = array_map(function (array $row) use ($actorLevel) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'mobile' => $row['mobile'],
                'email' => $row['email'],
                'username' => $row['username'],
                'role' => [
                    'id' => (int) $row['role_id'],
                    'name' => $row['role_name'],
                    'level' => (int) $row['role_level'],
                ],
                'is_super_admin' => (int) $row['is_super_admin'],
                'status' => $row['status'],
                'created_by' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
                'created_at' => $row['created_at'],
                'assignable' => (int) $row['role_level'] < $actorLevel,
            ];
        }, $rows);

        Response::success($data, [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ]);
    }

    public function store(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'manage_staff', 'You do not have permission to create staff');

        $data = $request->all();

        $name = trim((string) ($data['name'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        $roleId = (int) ($data['role_id'] ?? 0);
        $centreId = isset($data['centre_id']) && $data['centre_id'] !== '' ? (int) $data['centre_id'] : null;
        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;

        $errors = [];
        if ($name === '') {
            $errors['name'][] = 'Name is required';
        }
        if ($mobile === '' || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $errors['mobile'][] = 'A valid 10-digit Indian mobile number is required';
        }
        if ($roleId <= 0) {
            $errors['role_id'][] = 'Role is required';
        }
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $role = (new Role())->find($roleId);
        if ($role === null) {
            throw new ValidationException(['role_id' => ['The selected role is invalid']]);
        }

        $targetLevel = (int) $role['level'];
        $actorLevel = $this->currentLevel($actor);

        $targetName = (string) $role['name'];
        if ($targetName === 'SUPER_ADMIN' && !$this->rbac->isSuperAdmin($actor)) {
            throw new AuthorizationException('Only a Super Admin can create or assign the SUPER_ADMIN role');
        }

        if ($targetLevel >= $actorLevel) {
            throw new AuthorizationException('You cannot assign a role at or above your own level');
        }

        if ((new User())->byMobile($mobile) !== null) {
            throw new ConflictException('DUPLICATE_STAFF', 'A user with this mobile already exists');
        }

        if ($username !== null && trim((string) $username) !== '') {
            if ((new User())->byUsername((string) trim($username)) !== null) {
                throw new ValidationException(['username' => ['This username is already taken']]);
            }
        } else {
            $username = $this->generateUsername((string) $role['name'], $mobile);
        }

        if ($email !== null && trim((string) $email) !== '') {
            if (!filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException(['email' => ['A valid email address is required']]);
            }
            if ((new User())->byEmail((string) trim($email)) !== null) {
                throw new ValidationException(['email' => ['This email is already taken']]);
            }
        }

        $centre = null;
        if ($centreId !== null) {
            $centre = Database::selectOne(
                "SELECT * FROM procurement_centres WHERE id = ? AND deleted_at IS NULL",
                [$centreId]
            );
            if ($centre === null) {
                throw new ValidationException(['centre_id' => ['The selected centre is invalid']]);
            }
            if (!$this->scope->canAccessCentre($actor, $centreId)) {
                throw new AuthorizationException('You do not have access to the selected centre');
            }
            if (!in_array($targetName, ['CENTRE_MANAGER', 'CENTRE_OPERATOR'], true)) {
                throw new ValidationException(['centre_id' => ['Centre assignment is only valid for centre roles']]);
            }
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $username = trim((string) $username);

        Database::beginTransaction();

        try {
            $userId = (new User())->insert([
                'name' => $name,
                'mobile' => $mobile,
                'username' => $username,
                'email' => $email !== null ? trim((string) $email) : null,
                'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                'role_id' => $roleId,
                'status' => 'ACTIVE',
                'verification_status' => 'APPROVED',
                'mobile_verified_at' => date('Y-m-d H:i:s'),
                'password_set_at' => date('Y-m-d H:i:s'),
                'is_super_admin' => $targetName === 'SUPER_ADMIN' ? 1 : 0,
                'created_by' => (int) $actor['id'],
            ]);

            if ($centre !== null) {
                Database::insert('centre_staff', [
                    'centre_id' => (int) $centre['id'],
                    'user_id' => $userId,
                    'role' => $targetName,
                    'is_primary' => $targetName === 'CENTRE_MANAGER' ? 1 : 0,
                ]);
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
                    'role' => $targetName,
                    'role_id' => $roleId,
                    'centre_id' => $centre['id'] ?? null,
                ],
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $this->rbac->clearCache($userId);

        Response::created([
            'staff' => [
                'id' => $userId,
                'name' => $name,
                'mobile' => $mobile,
                'username' => $username,
                'email' => $email,
                'role' => $targetName,
                'centre_id' => $centre['id'] ?? null,
            ],
            'temporary_password' => $temporaryPassword,
            'message' => 'Staff account created',
        ]);
    }

    public function permissions(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'manage_staff', 'You do not have permission to modify staff permissions');

        $targetUserId = (int) $request->getParam('id');
        $target = (new User())->find($targetUserId);
        if ($target === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'Staff user not found');
        }

        $this->assertTargetWithinScope($actor, $target);

        $data = $request->all();
        $grant = $data['grant'] ?? [];
        $revoke = $data['revoke'] ?? [];

        if (!is_array($grant) || !is_array($revoke)) {
            throw new ValidationException(['grant' => ['grant must be an array'], 'revoke' => ['revoke must be an array']]);
        }

        $targetRole = (new Role())->find((int) $target['role_id']);
        $targetRoleName = $targetRole['name'] ?? '';
        $targetLevel = $targetRole !== null ? (int) $targetRole['level'] : 0;

        if ($targetRoleName === 'SUPER_ADMIN' && !$this->rbac->isSuperAdmin($actor)) {
            throw new AuthorizationException('Only a Super Admin can manage a SUPER_ADMIN account');
        }

        if ($targetLevel >= $this->currentLevel($actor) && !$this->rbac->isSuperAdmin($actor)) {
            throw new AuthorizationException('You cannot manage a user at or above your own level');
        }

        $permissionModel = new Permission();
        $userPermissionModel = new UserPermission();
        $changed = [];

        Database::beginTransaction();

        try {
            foreach ($grant as $permissionName) {
                if (!is_string($permissionName) || $permissionName === '') {
                    continue;
                }
                if (!$permissionModel->existsByName($permissionName)) {
                    continue;
                }
                if (!$this->rbac->can($actor, $permissionName)) {
                    throw new AuthorizationException('You cannot grant a permission you do not hold: ' . $permissionName);
                }
                $userPermissionModel->upsert($targetUserId, (int) $permissionModel->idByName($permissionName), true, (int) $actor['id']);
                $changed[] = ['permission' => $permissionName, 'action' => 'grant', 'granted' => true];
            }

            foreach ($revoke as $permissionName) {
                if (!is_string($permissionName) || $permissionName === '') {
                    continue;
                }
                if (!$permissionModel->existsByName($permissionName)) {
                    continue;
                }
                $userPermissionModel->upsert($targetUserId, (int) $permissionModel->idByName($permissionName), false, (int) $actor['id']);
                $changed[] = ['permission' => $permissionName, 'action' => 'revoke', 'granted' => false];
            }

            if (!empty($changed)) {
                $this->audit->log([
                    'user_id' => (int) $actor['id'],
                    'user_name' => $actor['name'] ?? '',
                    'user_role' => $actor['role'] ?? '',
                    'action' => 'PERMISSION_OVERRIDE',
                    'module' => 'PERMISSIONS',
                    'entity_type' => 'user',
                    'entity_id' => $targetUserId,
                    'new_value' => ['changes' => $changed],
                    'reason' => 'admin_role_permission_override',
                ]);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $this->rbac->clearCache($targetUserId);

        Response::success([
            'user_id' => $targetUserId,
            'changes' => $changed,
            'effective_permissions' => $this->rbac->effectivePermissions($targetUserId, $target),
            'message' => 'Permissions updated',
        ]);
    }

    public function assignRole(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'manage_staff', 'You do not have permission to change staff roles');

        $targetUserId = (int) $request->getParam('id');
        $target = (new User())->find($targetUserId);
        if ($target === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'Staff user not found');
        }

        $this->assertTargetWithinScope($actor, $target);

        $roleId = (int) $request->input('role_id', 0);
        if ($roleId <= 0) {
            throw new ValidationException(['role_id' => ['Role is required']]);
        }

        $newRole = (new Role())->find($roleId);
        if ($newRole === null) {
            throw new ValidationException(['role_id' => ['The selected role is invalid']]);
        }

        $newRoleName = (string) $newRole['name'];
        $newRoleLevel = (int) $newRole['level'];

        if ($newRoleName === 'SUPER_ADMIN' && !$this->rbac->isSuperAdmin($actor)) {
            throw new AuthorizationException('Only a Super Admin can create or assign the SUPER_ADMIN role');
        }

        if ($newRoleLevel >= $this->currentLevel($actor)) {
            throw new AuthorizationException('You cannot assign a role at or above your own level');
        }

        $oldRoleId = (int) $target['role_id'];
        $oldRole = (new Role())->find($oldRoleId);
        $oldRoleName = $oldRole['name'] ?? '';

        if (($oldRoleName === 'SUPER_ADMIN' || !empty($target['is_super_admin'])) && $newRoleName !== 'SUPER_ADMIN') {
            $this->assertNotLastSuperAdmin($targetUserId);
        }

        Database::beginTransaction();

        try {
            Database::update(
                'users',
                [
                    'role_id' => $roleId,
                    'is_super_admin' => $newRoleName === 'SUPER_ADMIN' ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ?',
                [$targetUserId]
            );

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'ROLE_CHANGED',
                'module' => 'PERMISSIONS',
                'entity_type' => 'user',
                'entity_id' => $targetUserId,
                'old_value' => ['role_id' => $oldRoleId, 'role' => $oldRoleName],
                'new_value' => ['role_id' => $roleId, 'role' => $newRoleName],
                'reason' => 'admin_role_assignment',
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $this->rbac->clearCache($targetUserId);

        Response::success([
            'user_id' => $targetUserId,
            'old_role' => $oldRoleName,
            'new_role' => $newRoleName,
            'message' => 'Role updated',
        ]);
    }

    public function roles(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'manage_staff', 'You do not have permission to view roles');

        $roles = (new Role())->allWithLevel();
        $actorLevel = $this->currentLevel($actor);
        $isSuperAdmin = $this->rbac->isSuperAdmin($actor);

        $data = array_map(function (array $role) use ($actorLevel, $isSuperAdmin) {
            return [
                'id' => (int) $role['id'],
                'name' => $role['name'],
                'display_name' => $role['display_name'],
                'level' => (int) $role['level'],
                'is_system' => (bool) $role['is_system'],
                'assignable' => $isSuperAdmin || (int) $role['level'] < $actorLevel,
            ];
        }, $roles);

        Response::success($data);
    }

    public function permissionCatalog(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'manage_staff', 'You do not have permission to view permissions');

        $catalog = (new Permission())->groupedCatalog();

        $result = [];
        foreach ($catalog as $module => $permissions) {
            $result[] = [
                'module' => $module,
                'permissions' => $permissions,
            ];
        }

        Response::success($result);
    }

    private function assertNotLastSuperAdmin(int $targetUserId): void
    {
        $countRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM users
             WHERE is_super_admin = 1 AND status = 'ACTIVE' AND deleted_at IS NULL",
            []
        );

        if ($countRow === null || (int) $countRow['total'] <= 1) {
            throw new ConflictException('SINGLE_SUPER_ADMIN', 'Cannot remove the last active Super Admin');
        }
    }

    private function assertTargetWithinScope(array $actor, array $target): void
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

        $targetRole = (new Role())->find((int) $target['role_id']);

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
            if ($targetRole !== null && strcasecmp((string) $targetRole['name'], 'FARMER') === 0 && (int) $target['id'] === (int) $actor['id']) {
                return;
            }
            throw new AuthorizationException('This staff member is outside your centre scope');
        }

        throw new AuthorizationException('You cannot manage this user');
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

    private function fallbackDistrictId(array $user): int
    {
        $row = Database::selectOne(
            "SELECT district_id FROM farmers WHERE user_id = ? AND deleted_at IS NULL LIMIT 1",
            [(int) $user['id']]
        );
        return $row !== null ? (int) $row['district_id'] : -1;
    }

    private function currentLevel(array $user): int
    {
        return (new Role())->level((int) ($user['role_id'] ?? 0));
    }

    private function requireActor(Request $request): array
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthorizationException('Authentication required');
        }
        return $user;
    }

    private function generateUsername(string $roleName, string $mobile): string
    {
        $prefix = strtolower(str_replace('_', '', $roleName));
        return $prefix . substr($mobile, -4);
    }

    private function generateTemporaryPassword(): string
    {
        $length = 10;
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        return $password;
    }
}