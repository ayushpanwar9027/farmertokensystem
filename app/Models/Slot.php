<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Slot extends BaseModel
{
    protected string $table = 'slots';
    protected bool $softDeletes = true;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_FULL = 'FULL';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_FULL,
        self::STATUS_CANCELLED,
    ];

    public const BOOKING_ACTIVE_STATUSES = ['PENDING', 'CONFIRMED'];

    public function findByCentreDateTime(int $centreId, string $date, string $startTime, string $endTime): ?array
    {
        return Database::selectOne(
            "SELECT * FROM slots
             WHERE centre_id = ? AND date = ? AND start_time = ? AND end_time = ?
               AND deleted_at IS NULL
             LIMIT 1",
            [$centreId, $date, $startTime, $endTime]
        ) ?: null;
    }

    public function bookedCount(int $slotId): int
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM bookings
             WHERE slot_id = ? AND deleted_at IS NULL
               AND status NOT IN ('CANCELLED', 'REVERSED', 'EXPIRED')",
            [$slotId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function hasActiveBookings(int $slotId): bool
    {
        return $this->bookedCount($slotId) > 0;
    }
}
