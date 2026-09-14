<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ProcurementCentre extends BaseModel
{
    protected string $table = 'procurement_centres';
    protected bool $softDeletes = true;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_CLOSED = 'CLOSED';

    public const VALID_STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_CLOSED];

    public function byCode(string $code): ?array
    {
        $row = Database::selectOne(
            "SELECT * FROM procurement_centres WHERE code = ? AND deleted_at IS NULL LIMIT 1",
            [$code]
        );
        return $row ?: null;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = "SELECT 1 FROM procurement_centres WHERE code = ? AND deleted_at IS NULL";
        $params = [$code];
        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }
        $sql .= " LIMIT 1";
        return Database::selectOne($sql, $params) !== null;
    }

    public function hasFutureBookings(int $centreId): bool
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM bookings
             WHERE centre_id = ?
               AND status IN ('PENDING', 'CONFIRMED')
               AND date >= CURDATE()
               AND deleted_at IS NULL",
            [$centreId]
        );
        return ($row !== null && (int) $row['total'] > 0);
    }

    public function districtId(int $centreId): ?int
    {
        $row = Database::selectOne(
            "SELECT district_id FROM procurement_centres WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$centreId]
        );
        return $row !== null ? (int) $row['district_id'] : null;
    }

    public function manager(int $centreId): ?array
    {
        $row = Database::selectOne(
            "SELECT u.id, u.name
             FROM centre_staff cs
             INNER JOIN users u ON u.id = cs.user_id AND u.deleted_at IS NULL
             WHERE cs.centre_id = ? AND cs.is_primary = 1
               AND cs.role = 'CENTRE_MANAGER'
               AND cs.deleted_at IS NULL
             ORDER BY cs.id DESC
             LIMIT 1",
            [$centreId]
        );
        return $row ?: null;
    }

    public function operators(int $centreId): array
    {
        return Database::select(
            "SELECT u.id, u.name
             FROM centre_staff cs
             INNER JOIN users u ON u.id = cs.user_id AND u.deleted_at IS NULL AND u.status = 'ACTIVE'
             WHERE cs.centre_id = ? AND cs.role = 'CENTRE_OPERATOR' AND cs.deleted_at IS NULL
             ORDER BY u.name",
            [$centreId]
        );
    }

    public function staff(int $centreId): array
    {
        return Database::select(
            "SELECT cs.id AS centre_staff_id, u.id, u.name, cs.role, cs.is_primary, u.status
             FROM centre_staff cs
             INNER JOIN users u ON u.id = cs.user_id AND u.deleted_at IS NULL
             WHERE cs.centre_id = ? AND cs.deleted_at IS NULL
             ORDER BY cs.role, u.name",
            [$centreId]
        );
    }

    public function countActiveInDistrict(int $districtId): int
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM procurement_centres
             WHERE district_id = ? AND status = 'ACTIVE' AND deleted_at IS NULL",
            [$districtId]
        );
        return (int) ($row['total'] ?? 0);
    }
}