<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class CentreStaff extends BaseModel
{
    protected string $table = 'centre_staff';
    protected bool $softDeletes = true;

    public function assignmentForUser(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    public function assignmentsForUser(int $userId): array
    {
        return $this->findMany('user_id', $userId);
    }

    public function assign(int $centreId, int $userId, string $role, bool $isPrimary = false): int
    {
        return $this->insert([
            'centre_id' => $centreId,
            'user_id' => $userId,
            'role' => $role,
            'is_primary' => $isPrimary ? 1 : 0,
        ]);
    }

    public function reassign(int $userId, int $centreId, string $role): bool
    {
        $rows = $this->where(['user_id' => $userId, 'role' => $role]);

        if (empty($rows)) {
            $this->assign($centreId, $userId, $role, $role === 'CENTRE_MANAGER');
            return true;
        }

        Database::update(
            'centre_staff',
            [
                'centre_id' => $centreId,
                'role' => $role,
                'is_primary' => $role === 'CENTRE_MANAGER' ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [(int) $rows[0]['id']]
        );
        return true;
    }

    public function removeForRole(int $userId, string $role): void
    {
        Database::update(
            'centre_staff',
            [
                'deleted_at' => date('Y-m-d H:i:s'),
            ],
            'user_id = ? AND role = ? AND deleted_at IS NULL',
            [$userId, $role]
        );
    }

    public function removeAllForUser(int $userId): void
    {
        Database::update(
            'centre_staff',
            [
                'deleted_at' => date('Y-m-d H:i:s'),
            ],
            'user_id = ? AND deleted_at IS NULL',
            [$userId]
        );
    }

    public function centreForUser(int $userId): ?int
    {
        $row = Database::selectOne(
            "SELECT centre_id FROM centre_staff
             WHERE user_id = ? AND deleted_at IS NULL
             ORDER BY is_primary DESC, id DESC
             LIMIT 1",
            [$userId]
        );
        return $row !== null ? (int) $row['centre_id'] : null;
    }
}