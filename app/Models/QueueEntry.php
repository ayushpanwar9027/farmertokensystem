<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class QueueEntry extends BaseModel
{
    protected string $table = 'queue_entries';
    protected bool $softDeletes = false;

    public const STATUS_WAITING = 'WAITING';
    public const STATUS_CALLED = 'CALLED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_SKIPPED = 'SKIPPED';
    public const STATUS_NO_SHOW = 'NO_SHOW';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTIVE_STATUSES = [self::STATUS_WAITING, self::STATUS_CALLED, self::STATUS_IN_PROGRESS];

    public function byBookingId(int $bookingId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM queue_entries WHERE booking_id = ? ORDER BY id DESC LIMIT 1",
            [$bookingId]
        ) ?: null;
    }

    public function countsByStatus(int $centreId, string $date): array
    {
        $rows = Database::select(
            "SELECT status, COUNT(*) AS total
             FROM queue_entries
             WHERE centre_id = ? AND date = ?
             GROUP BY status",
            [$centreId, $date]
        );

        $counts = [];
        foreach (self::ACTIVE_STATUSES as $status) {
            $counts[$status] = 0;
        }
        $counts[self::STATUS_COMPLETED] = 0;
        $counts[self::STATUS_SKIPPED] = 0;
        $counts[self::STATUS_NO_SHOW] = 0;
        $counts[self::STATUS_CANCELLED] = 0;

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}