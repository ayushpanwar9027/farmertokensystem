<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Role extends BaseModel
{
    protected string $table = 'roles';
    protected bool $softDeletes = true;

    public function byName(string $name): ?array
    {
        return $this->findBy('name', $name);
    }

    public function level(int $roleId): int
    {
        $role = $this->find($roleId);
        return $role !== null ? (int) $role['level'] : 0;
    }

    public function allWithLevel(bool $includeDeleted = false): array
    {
        $sql = "SELECT id, name, display_name, description, level, is_system FROM roles";
        if ($this->softDeletes && !$includeDeleted) {
            $sql .= " WHERE deleted_at IS NULL";
        }
        $sql .= " ORDER BY level DESC";
        return Database::select($sql);
    }

    public function assignableBy(int $userLevel): array
    {
        $roles = $this->allWithLevel();
        return array_values(array_filter(
            $roles,
            fn(array $role) => (int) $role['level'] < $userLevel
        ));
    }

    public function permissionIds(int $roleId): array
    {
        $rows = Database::select(
            "SELECT permission_id FROM role_permissions WHERE role_id = ?",
            [$roleId]
        );
        return array_map(fn($r) => (int) $r['permission_id'], $rows);
    }

    public function permissionNames(int $roleId): array
    {
        $rows = Database::select(
            "SELECT p.name
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?
             ORDER BY p.name",
            [$roleId]
        );
        return array_map(fn($r) => $r['name'], $rows);
    }
}
