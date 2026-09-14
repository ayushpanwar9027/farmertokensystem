<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\NotificationLog;

class NotificationService
{
    private NotificationTemplateService $templateService;
    private OneSignalService $oneSignal;
    private NotificationLog $logModel;

    public function __construct()
    {
        $this->templateService = new NotificationTemplateService();
        $this->oneSignal = new OneSignalService();
        $this->logModel = new NotificationLog();
    }

    public function dispatch(string $event, int $userId, array $params = []): array
    {
        $user = Database::selectOne(
            "SELECT id, mobile, locale FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$userId]
        );
        if ($user === null) {
            $this->logNotification("dispatch: user {$userId} not found, skipping");
            return ['in_app_created' => 0, 'push_enqueued' => 0, 'dedup_suppressed' => 1];
        }

        $locale = (string) ($user['locale'] ?? get_setting('notification_language_default', 'en'));
        if (!in_array($locale, ['en', 'hi'], true)) {
            $locale = 'en';
        }

        try {
            $rendered = $this->templateService->render($event, $locale, $params);
        } catch (\Throwable $e) {
            $this->logNotification("dispatch TEMPLATE_NOT_FOUND: event={$event} user={$userId} — " . $e->getMessage());
            throw $e;
        }

        $entityId = $params['entity_id'] ?? null;
        $dedupKey = $event . '_' . ($entityId !== null ? (string) $entityId : '') . '_' . (string) $userId;
        $dedupWindow = (int) config('push.dedup_window_seconds', 3600);

        if ($this->logModel->dedupExists($dedupKey, $dedupWindow)) {
            $this->logNotification("dispatch: dedup suppressed key={$dedupKey}");
            return ['in_app_created' => 0, 'push_enqueued' => 0, 'dedup_suppressed' => 1];
        }

        $inAppCreated = 0;
        $pushEnqueued = 0;

        $inAppPayload = json_encode([
            'event' => $event,
            'entity_id' => $entityId,
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE);

        try {
            Database::insert('notifications', [
                'user_id' => $userId,
                'type' => $event,
                'title' => $rendered['heading'],
                'message' => $rendered['message'],
                'data' => $inAppPayload,
                'channel' => 'IN_APP',
            ]);
            $inAppCreated = 1;
        } catch (\Throwable $e) {
            $this->logNotification("dispatch: in-app insert failed for event={$event} user={$userId}: " . $e->getMessage());
        }

        $pushEnabled = (bool) get_setting('push_enabled', true);
        $maxAttempts = max(1, (int) config('push.retry_max', 3));

        try {
            $this->logModel->insert([
                'notification_id' => null,
                'user_id' => $userId,
                'recipient' => (string) ($user['mobile'] ?? ''),
                'channel' => 'IN_APP',
                'event_type' => $event,
                'event_ref' => $dedupKey,
                'status' => 'SENT',
                'attempt_count' => 1,
                'max_attempts' => $maxAttempts,
                'sent_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->logNotification("dispatch: in-app log insert failed user={$userId}: " . $e->getMessage());
        }

        if ($pushEnabled) {
            $devices = Database::select(
                "SELECT id, onesignal_player_id FROM user_devices
                 WHERE user_id = ? AND onesignal_player_id IS NOT NULL AND onesignal_player_id <> ''",
                [$userId]
            );

            $playerIds = [];
            foreach ($devices as $device) {
                $playerId = trim((string) $device['onesignal_player_id']);
                if ($playerId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $playerId)) {
                    $this->logNotification("dispatch: INVALID_RECIPIENT invalid player_id for device={$device['id']} user={$userId}");
                    continue;
                }

                $deviceRef = $dedupKey . '_d' . (int) $device['id'];
                try {
                    $this->logModel->insert([
                        'notification_id' => null,
                        'user_id' => $userId,
                        'recipient' => $playerId,
                        'channel' => 'PUSH',
                        'event_type' => $event,
                        'event_ref' => $deviceRef,
                        'status' => 'PENDING',
                        'attempt_count' => 0,
                        'max_attempts' => $maxAttempts,
                        'provider_response' => json_encode($params, JSON_UNESCAPED_UNICODE),
                    ]);
                    $pushEnqueued++;
                    $playerIds[] = $playerId;
                } catch (\Throwable $e) {
                    $this->logNotification("dispatch: push log insert failed user={$userId}: " . $e->getMessage());
                }
            }

            $immediate = (bool) config('push.immediate', true);
            if ($immediate && !empty($playerIds)) {
                $this->attemptImmediatePush($userId, $rendered, $event, $entityId, $params, $playerIds);
            }
        }

        $this->logNotification("dispatch: event={$event} user={$userId} in_app={$inAppCreated} push={$pushEnqueued}");

        return [
            'in_app_created' => $inAppCreated,
            'push_enqueued' => $pushEnqueued,
            'dedup_suppressed' => 0,
        ];
    }

    public function sendOtp(string $mobile, string $otp, string $purpose): bool
    {
        $templateId = $this->templateForPurpose($purpose);
        $locale = (string) get_setting('notification_language_default', 'en');
        $expiryMinutes = (int) get_setting('otp_expiry_minutes', 5);

        $message = (new OtpTemplateService())->render($templateId, $locale, [
            'appname' => (string) get_setting('system_name', 'Farmer Procurement System'),
            'otp' => $otp,
            'expiry' => max(1, $expiryMinutes),
        ]);

        $smsEnabled = (bool) get_setting('sms_enabled', true);
        $gatewayKey = trim((string) config('otp.otp_api_key', ''));
        $senderId = trim((string) config('otp.otp_sender_id', 'FPS'));
        $template = trim((string) config('otp.otp_template_id', ''));

        $env = (string) config('env', 'development');
        $keysAvailable = $gatewayKey !== '' && $gatewayKey !== 'your-otp-gateway-api-key';

        if ($smsEnabled && $keysAvailable) {
            $sent = $this->sendViaOtpGateway($mobile, $otp, $senderId, $template, $templateId);
            if ($sent) {
                $this->logSecurity("OTP sent: purpose={$templateId} mobile=" . $this->maskMobile($mobile));
                return true;
            }

            $this->logSecurity("OTP gateway failed: purpose={$templateId} mobile=" . $this->maskMobile($mobile) . " — enqueue retry");
            return false;
        }

        if ($env !== 'production') {
            $this->logNotification("OTP dev fallback: purpose={$templateId} mobile=" . $this->maskMobile($mobile) . " otp=" . substr($otp, 0, 2) . "****");
        }

        return $this->sendViaLog($mobile, $message, $templateId);
    }

    public function sendPush(int $userId, string $title, string $message): bool
    {
        $pushEnabled = (bool) get_setting('push_enabled', true);
        $appId = trim((string) config('onesignal.onesignal_app_id', ''));
        $restKey = trim((string) config('onesignal.onesignal_rest_api_key', ''));

        if (!$pushEnabled || $appId === '' || $appId === 'your-onesignal-app-id' || $restKey === '' || $restKey === 'your-onesignal-rest-api-key') {
            $this->logNotification("WARN: OneSignal push not configured or disabled — push skipped (user {$userId})");
            return false;
        }

        $devices = Database::select(
            "SELECT onesignal_player_id FROM user_devices
             WHERE user_id = ? AND onesignal_player_id IS NOT NULL AND onesignal_player_id <> ''",
            [$userId]
        );

        $playerIds = [];
        foreach ($devices as $d) {
            $pid = trim((string) $d['onesignal_player_id']);
            if ($pid !== '' && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $pid)) {
                $playerIds[] = $pid;
            }
        }

        if (empty($playerIds)) {
            $this->logNotification("WARN: No valid player IDs for user {$userId} — push skipped");
            return false;
        }

        $result = $this->oneSignal->sendPush(
            $playerIds,
            ['en' => $title, 'hi' => $title],
            ['en' => $message, 'hi' => $message]
        );

        return $result['ok'];
    }

    public function enqueueEvent(array $data, string $eventType): void
    {
        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $entityId = $data['entity_id'] ?? null;
        $entityType = $data['entity_type'] ?? 'unknown';

        $this->dispatch($eventType, $userId, array_merge($data, [
            'entity_id' => $entityId,
            'entity_type' => $entityType,
        ]));
    }

    private function attemptImmediatePush(
        int $userId,
        array $rendered,
        string $event,
        ?int $entityId,
        array $params,
        array $playerIds
    ): void {
        $result = $this->oneSignal->sendPush(
            $playerIds,
            ['en' => $rendered['heading_en'], 'hi' => $rendered['heading_hi']],
            ['en' => $rendered['message_en'], 'hi' => $rendered['message_hi']],
            ['event' => $event, 'entity_id' => $entityId, 'params' => $params]
        );

        $dedupKey = $event . '_' . ($entityId !== null ? (string) $entityId : '') . '_' . (string) $userId;

        $logRows = Database::select(
            "SELECT id, attempt_count, max_attempts FROM notification_logs
             WHERE event_ref LIKE ? AND channel = 'PUSH' AND status = 'PENDING'
             ORDER BY id ASC",
            [$dedupKey . '_d%']
        );

        if ($logRows === false) {
            $logRows = [];
        }

        foreach ($logRows as $row) {
            if ($result['ok']) {
                $this->logModel->markSent((int) $row['id']);
            } else {
                $attemptCount = (int) $row['attempt_count'] + 1;
                if ($attemptCount < (int) $row['max_attempts']) {
                    $nextRetry = $this->calculateNextRetry($attemptCount);
                    $this->logModel->markRetry((int) $row['id'], $attemptCount, $nextRetry, $result['error'] ?? 'unknown');
                } else {
                    $this->logModel->markFailed((int) $row['id'], $result['error'] ?? 'max attempts reached');
                }
            }
        }
    }

    public function retryPending(): int
    {
        $logs = $this->logModel->pendingPushForRetry();
        $sent = 0;

        foreach ($logs as $log) {
            $playerId = trim((string) $log['recipient']);
            if ($playerId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $playerId)) {
                $this->logModel->markFailed((int) $log['id'], 'INVALID_RECIPIENT');
                continue;
            }

            $event = (string) $log['event_type'];
            $locale = $this->localeForUser((int) ($log['user_id'] ?? 0));
            $params = $this->storedParams($log);
            $result = $this->sendOne($event, $locale, $playerId, $params);

            if ($result['ok']) {
                $this->logModel->markSent((int) $log['id']);
                $sent++;
            } else {
                $attemptCount = (int) $log['attempt_count'] + 1;
                if ($attemptCount < (int) $log['max_attempts']) {
                    $this->logModel->markRetry((int) $log['id'], $attemptCount, $this->calculateNextRetry($attemptCount), $result['error'] ?? 'unknown');
                } else {
                    $this->logModel->markFailed((int) $log['id'], $result['error'] ?? 'max attempts reached');
                    $this->failMonitoring((int) $log['id'], (string) $log['event_type']);
                }
            }
        }

        return $sent;
    }

    public function retryFailed(): int
    {
        $logs = Database::select(
            "SELECT * FROM notification_logs
             WHERE channel = 'PUSH' AND status = 'RETRY'
             AND next_retry_at IS NOT NULL AND next_retry_at <= NOW()
             AND attempt_count < max_attempts
             ORDER BY id ASC
             LIMIT 100"
        );

        $sent = 0;
        foreach ($logs as $log) {
            $playerId = trim((string) $log['recipient']);
            if ($playerId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $playerId)) {
                $this->logModel->markFailed((int) $log['id'], 'INVALID_RECIPIENT');
                continue;
            }

            $event = (string) $log['event_type'];
            $locale = $this->localeForUser((int) ($log['user_id'] ?? 0));
            $params = $this->storedParams($log);
            $result = $this->sendOne($event, $locale, $playerId, $params);

            if ($result['ok']) {
                $this->logModel->markSent((int) $log['id']);
                $sent++;
            } else {
                $attemptCount = (int) $log['attempt_count'] + 1;
                if ($attemptCount < (int) $log['max_attempts']) {
                    $this->logModel->markRetry((int) $log['id'], $attemptCount, $this->calculateNextRetry($attemptCount), $result['error'] ?? 'unknown');
                } else {
                    $this->logModel->markFailed((int) $log['id'], $result['error'] ?? 'max attempts reached');
                    $this->failMonitoring((int) $log['id'], (string) $log['event_type']);
                }
            }
        }

        return $sent;
    }

    private function sendOne(string $event, string $locale, string $playerId, array $params): array
    {
        try {
            $rendered = $this->templateService->render($event, $locale, $params);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'TEMPLATE_ERROR: ' . $e->getMessage()];
        }

        return $this->oneSignal->sendPush(
            [$playerId],
            ['en' => $rendered['heading_en'], 'hi' => $rendered['heading_hi']],
            ['en' => $rendered['message_en'], 'hi' => $rendered['message_hi']],
            ['event' => $event, 'entity_id' => $params['entity_id'] ?? null]
        );
    }

    private function localeForUser(int $userId): string
    {
        if ($userId <= 0) {
            return 'en';
        }
        $user = Database::selectOne(
            "SELECT locale FROM users WHERE id = ? LIMIT 1",
            [$userId]
        );
        $locale = $user !== null ? (string) ($user['locale'] ?? 'en') : 'en';
        return in_array($locale, ['en', 'hi'], true) ? $locale : 'en';
    }

    private function storedParams(array $log): array
    {
        $decoded = json_decode((string) ($log['provider_response'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function failMonitoring(int $logId, string $event): void
    {
        $this->logNotification("NOTIFICATION_FAILED_PERMANENT: log={$logId} event={$event} — max attempts reached");
        try {
            (new AuditService())->log([
                'action' => 'NOTIFICATION_FAILED',
                'module' => 'NOTIFICATIONS',
                'entity_type' => 'notification_log',
                'entity_id' => $logId,
                'new_value' => ['event' => $event, 'status' => 'FAILED'],
                'reason' => 'max_attempts_reached',
            ]);
        } catch (\Throwable $ignored) {
        }
    }

    private function sendViaOtpGateway(string $mobile, string $otp, string $senderId, string $templateId, string $purpose): bool
    {
        $url = 'https://api.msg91.com/api/v5/otp';

        $payload = [
            'authkey' => trim((string) config('otp.otp_api_key', '')),
            'mobile' => $mobile,
            'otp' => $otp,
        ];

        if ($senderId !== '') {
            $payload['sender'] = $senderId;
        }
        if ($templateId !== '' && $templateId !== 'your-otp-template-id') {
            $payload['template_id'] = $templateId;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $this->logNotification("OTP gateway curl error: " . substr($curlError, 0, 100));
            return false;
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            $this->logNotification("OTP gateway invalid response");
            return false;
        }

        $success = isset($decoded['type']) && (string) $decoded['type'] === 'success';
        if (!$success) {
            $this->logNotification("OTP gateway error: " . json_encode($decoded, JSON_UNESCAPED_UNICODE));
        }

        return $success;
    }

    private function sendViaLog(string $mobile, string $message, string $templateId): bool
    {
        $this->logNotification("OTP dev: mobile=" . $this->maskMobile($mobile) . " template={$templateId}");

        try {
            Database::insert('notification_logs', [
                'user_id' => null,
                'recipient' => $mobile,
                'channel' => 'SMS',
                'event_type' => $templateId,
                'event_ref' => $templateId . '_' . substr(sha1($mobile . time()), 0, 12),
                'status' => 'SENT',
                'attempt_count' => 1,
                'max_attempts' => (int) get_setting('notification_retry_count', 3),
                'sent_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
        }

        return true;
    }

    private function templateForPurpose(string $purpose): string
    {
        return match ($purpose) {
            'REGISTER' => 'otp.register',
            'LOGIN_2FA', '2FA_ENABLE', '2FA_STEP_UP' => 'otp.login_2fa',
            'PASSWORD_RESET' => 'otp.password_reset',
            'MOBILE_CHANGE' => 'otp.mobile_change',
            default => 'otp.login_2fa',
        };
    }

    private function calculateNextRetry(int $attemptCount): string
    {
        $baseSeconds = (int) config('push.backoff_base_seconds', 60);
        $capSeconds = (int) config('push.backoff_cap_seconds', 1800);
        $interval = $baseSeconds * pow(2, $attemptCount - 1);
        $interval = min($interval, $capSeconds);
        return date('Y-m-d H:i:s', time() + (int) $interval);
    }

    private function maskMobile(string $mobile): string
    {
        $len = strlen($mobile);
        if ($len <= 4) {
            return '****';
        }
        return '+91****' . substr($mobile, -4);
    }

    private function logNotification(string $message): void
    {
        $logPath = storage_path('logs/notification.log');
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logPath,
            json_encode([
                'timestamp' => gmdate('c'),
                'level' => 'INFO',
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function logSecurity(string $message): void
    {
        $logPath = storage_path('logs/security.log');
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logPath,
            json_encode([
                'timestamp' => gmdate('c'),
                'level' => 'INFO',
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}