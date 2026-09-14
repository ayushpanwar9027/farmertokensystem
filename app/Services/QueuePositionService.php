<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\QueueEntry;

class QueuePositionService
{
    public function nextPosition(int $centreId, string $date): int
    {
        $row = Database::selectOne(
            "SELECT COALESCE(MAX(position), 0) + 1 AS next_position
             FROM queue_entries
             WHERE centre_id = ? AND date = ?",
            [$centreId, $date]
        );

        return (int) ($row['next_position'] ?? 1);
    }

    public function aheadCount(int $centreId, string $date, int $position): int
    {
        $ids = implode(',', array_fill(0, count(QueueEntry::ACTIVE_STATUSES), '?'));
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM queue_entries
             WHERE centre_id = ? AND date = ? AND position < ? AND status IN ({$ids})",
            array_merge([$centreId, $date, $position], QueueEntry::ACTIVE_STATUSES)
        );

        return (int) ($row['total'] ?? 0);
    }

    public function renumber(int $centreId, string $date): int
    {
        $ids = implode(',', array_fill(0, count(QueueEntry::ACTIVE_STATUSES), '?'));
        $rows = Database::select(
            "SELECT id, position
             FROM queue_entries
             WHERE centre_id = ? AND date = ? AND status IN ({$ids})
             ORDER BY position ASC, id ASC",
            array_merge([$centreId, $date], QueueEntry::ACTIVE_STATUSES)
        );

        $changed = 0;
        foreach ($rows as $index => $row) {
            $expected = $index + 1;
            if ((int) $row['position'] !== $expected) {
                Database::update(
                    'queue_entries',
                    ['position' => $expected],
                    'id = ?',
                    [(int) $row['id']]
                );
                $changed++;
            }
        }

        return $changed;
    }
}