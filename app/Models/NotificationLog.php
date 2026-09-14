<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class NotificationLog extends BaseModel
{
    protected string $table = 'notification_logs';
    protected bool $softDeletes = false;

    public function pendingPushForRetry(): array
    {
        return Database::select(
            "SELECT * FROM notification_logs
             WHERE channel = 'PUSH' AND status IN ('PENDING', 'RETRY')
             AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             AND attempt_count < max_attempts
             ORDER BY id ASC
             LIMIT 100"
        );
    }

    public function pendingImmediate(): array
    {
        return Database::select(
            "SELECT * FROM notification_logs
             WHERE channel = 'PUSH' AND status = 'PENDING'
             AND attempt_count = 0
             ORDER BY id ASC
             LIMIT 50"
        );
    }

    public function markSent(int $id): void
    {
        Database::update(
            $this->table,
            ['status' => 'SENT', 'sent_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }

    public function markFailed(int $id, string $error, ?string $providerResponse = null): void
    {
        Database::update(
            $this->table,
            [
                'status' => 'FAILED',
                'error_message' => $error,
                'provider_response' => $providerResponse,
            ],
            'id = ?',
            [$id]
        );
    }

    public function markRetry(int $id, int $attemptCount, string $nextRetryAt, ?string $error = null): void
    {
        Database::update(
            $this->table,
            [
                'status' => 'RETRY',
                'attempt_count' => $attemptCount,
                'next_retry_at' => $nextRetryAt,
                'error_message' => $error,
            ],
            'id = ?',
            [$id]
        );
    }

    public function dedupExists(string $dedupKey, int $windowSeconds): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
        $row = Database::selectOne(
            "SELECT 1 FROM notification_logs
             WHERE event_ref LIKE ? AND created_at >= ? AND status IN ('PENDING','SENT','RETRY')
             LIMIT 1",
            [$dedupKey . '%', $cutoff]
        );
        return $row !== null;
    }

    public function todaySummary(): array
    {
        $today = date('Y-m-d');
        $rows = Database::select(
            "SELECT status, COUNT(*) AS total FROM notification_logs
             WHERE DATE(created_at) = ? AND channel = 'PUSH'
             GROUP BY status",
            [$today]
        );

        $summary = ['sent' => 0, 'failed' => 0, 'pending' => 0, 'retry' => 0];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['status']);
            $summary[$key] = (int) $row['total'];
        }
        return $summary;
    }

    public function adminList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND nl.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['event'])) {
            $where .= ' AND nl.event_type = ?';
            $params[] = (string) $filters['event'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND nl.created_at >= ?';
            $params[] = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND nl.created_at <= ?';
            $params[] = (string) $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (nl.event_type LIKE ? OR nl.recipient LIKE ? OR nl.error_message LIKE ?)';
            $q = '%' . (string) $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM notification_logs nl WHERE {$where}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT nl.*, u.name AS user_name
             FROM notification_logs nl
             LEFT JOIN users u ON u.id = nl.user_id
             WHERE {$where}
             ORDER BY nl.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }
}
