<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Models\Role;
use App\Models\UserPermission;

class RbacService
{
    private const SUPER_ADMIN_ROLE_KEY = 'rbac.super_admin_role_id';

    private static array $effectiveCache = [];

    public function can(?array $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $effective = $this->effectivePermissions($userId, $user);
        return in_array($permission, $effective, true);
    }

    public function assertCan(?array $user, string $permission, string $message = 'You do not have permission to perform this action'): void
    {
        if (!$this->can($user, $permission)) {
            throw new AuthorizationException($message);
        }
    }

    public function assertRoleLevel(?array $user, int $minLevel, ?array $allowedRoles = null): void
    {
        if ($user === null) {
            throw new AuthorizationException('Authentication required');
        }

        $roleId = (int) ($user['role_id'] ?? 0);
        $roleName = (string) ($user['role'] ?? '');

        if ($allowedRoles !== null && in_array($roleName, $allowedRoles, true)) {
            return;
        }

        if ($this->isSuperAdmin($user)) {
            return;
        }

        $level = (new Role())->level($roleId);
        if ($level < $minLevel) {
            throw new AuthorizationException('Your role does not have sufficient access');
        }
    }

    public function effectivePermissions(int $userId, ?array $user = null): array
    {
        if (!isset(self::$effectiveCache[$userId])) {
            self::$effectiveCache[$userId] = $this->computeEffectivePermissions($userId, $user);
        }
        return self::$effectiveCache[$userId];
    }

    public function clearCache(int $userId): void
    {
        unset(self::$effectiveCache[$userId]);
    }

    public function clearAllCache(): void
    {
        self::$effectiveCache = [];
    }

    private function computeEffectivePermissions(int $userId, ?array $user): array
    {
        $row = $user ?? Database::selectOne(
            "SELECT u.id, u.role_id, u.is_super_admin
             FROM users u WHERE u.id = ? AND u.deleted_at IS NULL",
            [$userId]
        );

        if ($row === null) {
            return [];
        }

        if (!empty($row['is_super_admin'])) {
            return $this->allPermissionNames();
        }

        $roleId = (int) ($row['role_id'] ?? 0);
        $base = (new Role())->permissionNames($roleId);
        $base = array_fill_keys($base, true);

        $negativePriority = (bool) config('rbac.permission_negative_priority', true);

        $overrides = (new UserPermission())->overridesFor($userId);
        foreach ($overrides as $permission => $granted) {
            if ($granted) {
                $base[$permission] = true;
            } elseif ($negativePriority) {
                $base[$permission] = false;
            }
        }

        $effective = [];
        foreach ($base as $permission => $granted) {
            if ($granted) {
                $effective[] = $permission;
            }
        }

        sort($effective);
        return array_values($effective);
    }

    public function grantPermission(int $userId, string $permissionName, int $createdBy): bool
    {
        $permissionId = (new \App\Models\Permission())->idByName($permissionName);
        if ($permissionId === null) {
            return false;
        }

        (new UserPermission())->upsert($userId, $permissionId, true, $createdBy);
        $this->clearCache($userId);
        return true;
    }

    public function revokePermission(int $userId, string $permissionName, int $createdBy): bool
    {
        $permissionId = (new \App\Models\Permission())->idByName($permissionName);
        if ($permissionId === null) {
            return false;
        }

        (new UserPermission())->upsert($userId, $permissionId, false, $createdBy);
        $this->clearCache($userId);
        return true;
    }

    public function removeOverride(int $userId, string $permissionName): bool
    {
        $permissionId = (new \App\Models\Permission())->idByName($permissionName);
        if ($permissionId === null) {
            return false;
        }

        (new UserPermission())->remove($userId, $permissionId);
        $this->clearCache($userId);
        return true;
    }

    public function isSuperAdmin(array $user): bool
    {
        if (!empty($user['is_super_admin'])) {
            return true;
        }

        $superAdminRoleId = (int) config('rbac.super_admin_role_id', 1);
        $roleId = (int) ($user['role_id'] ?? 0);
        $role = (string) ($user['role'] ?? '');

        return $role === 'SUPER_ADMIN'
            || ($superAdminRoleId > 0 && $roleId === $superAdminRoleId);
    }

    public function superAdminRoleId(): int
    {
        return (int) config('rbac.super_admin_role_id', 1);
    }

    public function allPermissionNames(): array
    {
        static $names = null;
        if ($names === null) {
            $names = (new \App\Models\Permission())->allNames();
        }
        return $names;
    }
}
