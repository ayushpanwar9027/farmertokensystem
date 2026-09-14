<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\LoginHistory;

class LoginHistoryService
{
    public function record(array $entry): int
    {
        $data = [
            'user_id' => $entry['user_id'] ?? null,
            'mobile' => $entry['mobile'] ?? null,
            'login_at' => $entry['login_at'] ?? date('Y-m-d H:i:s'),
            'logout_at' => $entry['logout_at'] ?? null,
            'ip_address' => $entry['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $entry['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'platform' => $entry['platform'] ?? null,
            'device_name' => $entry['device_name'] ?? null,
            'status' => $entry['status'] ?? 'SUCCESS',
            'failure_reason' => $entry['failure_reason'] ?? null,
            'session_id' => $entry['session_id'] ?? null,
        ];

        return (new LoginHistory())->insert($data);
    }

    public function recordSuccess(int $userId, string $mobile, ?int $sessionId, array $context = []): int
    {
        return $this->record([
            'user_id' => $userId,
            'mobile' => $mobile,
            'status' => 'SUCCESS',
            'session_id' => $sessionId,
            'platform' => $context['platform'] ?? null,
            'device_name' => $context['device_name'] ?? null,
        ]);
    }

    public function recordFailure(?int $userId, string $mobile, string $reason, array $context = []): int
    {
        return $this->record([
            'user_id' => $userId,
            'mobile' => $mobile,
            'status' => 'FAILURE',
            'failure_reason' => $reason,
            'platform' => $context['platform'] ?? null,
            'device_name' => $context['device_name'] ?? null,
        ]);
    }

    public function recordStepUp(int $userId, string $mobile, string $event, array $context = []): int
    {
        return $this->record([
            'user_id' => $userId,
            'mobile' => $mobile,
            'status' => 'SUCCESS',
            'failure_reason' => $event,
            'platform' => $context['platform'] ?? null,
            'device_name' => $context['device_name'] ?? null,
        ]);
    }

    public function recordLogout(int $sessionId): void
    {
        if ($sessionId === null) {
            return;
        }
        Database::query(
            "UPDATE login_history SET logout_at = ? WHERE session_id = ? AND logout_at IS NULL ORDER BY login_at DESC LIMIT 1",
            [date('Y-m-d H:i:s'), $sessionId]
        );
    }
}
