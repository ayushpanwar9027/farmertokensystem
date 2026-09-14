<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationLog;

class OneSignalService
{
    private const API_URL = 'https://onesignal.com/api/v1/notifications';

    public function sendPush(
        array $playerIds,
        array $headings,
        array $contents,
        array $data = [],
        ?string $url = null
    ): array {
        $appId = trim((string) config('onesignal.onesignal_app_id', ''));
        $restKey = trim((string) config('onesignal.onesignal_rest_api_key', ''));
        $timeout = (int) config('onesignal.timeout', 10);

        if ($appId === '' || $appId === 'your-onesignal-app-id'
            || $restKey === '' || $restKey === 'your-onesignal-rest-api-key') {
            $this->logNotification('OneSignal keys not configured — push skipped');
            return ['ok' => false, 'error' => 'ONESIGNAL_NOT_CONFIGURED', 'sent' => 0];
        }

        if (empty($playerIds)) {
            return ['ok' => false, 'error' => 'NO_PLAYER_IDS', 'sent' => 0];
        }

        $payload = [
            'app_id' => $appId,
            'include_player_ids' => $playerIds,
            'headings' => $headings,
            'contents' => $contents,
            'data' => $data,
        ];

        if ($url !== null && $url !== '') {
            $payload['url'] = $url;
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $restKey,
                'Content-Type: application/json; charset=utf-8',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $this->logNotification('OneSignal curl error: ' . $this->maskString($curlError));
            return ['ok' => false, 'error' => $curlError, 'sent' => 0];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            $this->logNotification('OneSignal invalid response');
            return ['ok' => false, 'error' => 'INVALID_RESPONSE', 'sent' => 0];
        }

        if (isset($decoded['id'])) {
            $this->logNotification('OneSignal push sent, id=' . substr((string) $decoded['id'], 0, 8) . '...');
            return ['ok' => true, 'id' => $decoded['id'] ?? null, 'sent' => count($playerIds)];
        }

        $errorMsg = $decoded['errors'][0]['message'] ?? $decoded['message'] ?? 'Unknown error';
        $this->logNotification('OneSignal push failed: ' . $this->maskString((string) $errorMsg));
        return ['ok' => false, 'error' => (string) $errorMsg, 'http_code' => $httpCode, 'sent' => 0];
    }

    public function isConfigured(): bool
    {
        $appId = trim((string) config('onesignal.onesignal_app_id', ''));
        $restKey = trim((string) config('onesignal.onesignal_rest_api_key', ''));
        return $appId !== '' && $appId !== 'your-onesignal-app-id'
            && $restKey !== '' && $restKey !== 'your-onesignal-rest-api-key';
    }

    private function maskString(string $value): string
    {
        if (strlen($value) <= 8) {
            return '****';
        }
        return substr($value, 0, 4) . '****' . substr($value, -4);
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
                'service' => 'OneSignal',
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
