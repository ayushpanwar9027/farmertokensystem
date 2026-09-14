<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class UserPermission extends BaseModel
{
    protected string $table = 'user_permissions';
    protected bool $softDeletes = false;

    public function overridesFor(int $userId): array
    {
        $rows = Database::select(
            "SELECT p.name AS permission, up.granted, up.permission_id
             FROM user_permissions up
             INNER JOIN permissions p ON p.id = up.permission_id
             WHERE up.user_id = ?",
            [$userId]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['permission']] = (bool) $row['granted'];
        }
        return $result;
    }

    public function overridesWithMeta(int $userId): array
    {
        return Database::select(
            "SELECT up.id, up.permission_id, p.name, p.display_name, p.module, up.granted, up.created_at
             FROM user_permissions up
             INNER JOIN permissions p ON p.id = up.permission_id
             WHERE up.user_id = ?
             ORDER BY p.module, p.name",
            [$userId]
        );
    }

    public function upsert(int $userId, int $permissionId, bool $granted, int $createdBy): void
    {
        $existing = Database::selectOne(
            "SELECT id FROM user_permissions WHERE user_id = ? AND permission_id = ?",
            [$userId, $permissionId]
        );

        if ($existing !== null) {
            Database::update(
                'user_permissions',
                ['granted' => $granted ? 1 : 0, 'created_by' => $createdBy],
                'id = ?',
                [(int) $existing['id']]
            );
        } else {
            Database::insert('user_permissions', [
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'granted' => $granted ? 1 : 0,
                'created_by' => $createdBy,
            ]);
        }
    }

    public function remove(int $userId, int $permissionId): int
    {
        return Database::delete(
            'user_permissions',
            'user_id = ? AND permission_id = ?',
            [$userId, $permissionId]
        );
    }

    public function removeAllFor(int $userId): int
    {
        return Database::delete('user_permissions', 'user_id = ?', [$userId]);
    }
}
