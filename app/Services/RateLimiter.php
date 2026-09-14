<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class RateLimiter
{
    public function check(string $key, int $limit, int $windowSeconds): array
    {
        $windowStart = time() - $windowSeconds;

        Database::query(
            "DELETE FROM rate_limit_logs WHERE limiter_key = ? AND window_start <= ?",
            [$key, date('Y-m-d H:i:s', $windowStart)]
        );

        $row = Database::selectOne(
            "SELECT hits, window_start FROM rate_limit_logs WHERE limiter_key = ? ORDER BY window_start DESC LIMIT 1",
            [$key]
        );

        if ($row === null) {
            return [
                'exceeded' => false,
                'hits' => 0,
                'remaining' => $limit - 1,
                'limit' => $limit,
                'reset' => time() + $windowSeconds,
                'retry_after' => 0,
            ];
        }

        $windowStartTime = strtotime($row['window_start']);
        $hits = (int) $row['hits'];

        if (time() > $windowStartTime + $windowSeconds) {
            return [
                'exceeded' => false,
                'hits' => 0,
                'remaining' => $limit - 1,
                'limit' => $limit,
                'reset' => time() + $windowSeconds,
                'retry_after' => 0,
            ];
        }

        $remaining = max(0, $limit - $hits - 1);
        $reset = $windowStartTime + $windowSeconds;
        $retryAfter = max(0, $reset - time());

        return [
            'exceeded' => $hits >= $limit,
            'hits' => $hits,
            'remaining' => $remaining,
            'limit' => $limit,
            'reset' => $reset,
            'retry_after' => $retryAfter,
        ];
    }

    public function increment(string $key, int $windowSeconds): void
    {
        $now = new \DateTime();
        $windowStart = $now->modify("-{$windowSeconds} seconds")->format('Y-m-d H:i:s');

        $existing = Database::selectOne(
            "SELECT id, hits FROM rate_limit_logs WHERE limiter_key = ? AND window_start >= ? ORDER BY window_start DESC LIMIT 1",
            [$key, $windowStart]
        );

        if ($existing !== null) {
            Database::query(
                "UPDATE rate_limit_logs SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?",
                [(int) $existing['id']]
            );
        } else {
            Database::insert('rate_limit_logs', [
                'limiter_key' => $key,
                'hits' => 1,
                'window_start' => date('Y-m-d H:i:s'),
                'window_end' => date('Y-m-d H:i:s', time() + $windowSeconds),
                'last_hit_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function reset(string $key): void
    {
        Database::query(
            "DELETE FROM rate_limit_logs WHERE limiter_key = ?",
            [$key]
        );
    }
}
