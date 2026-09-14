<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SecurityException;
use App\Exceptions\ValidationException;
use App\Models\SystemSecret;

class SecretService
{
    private const CIPHER = 'aes-256-gcm';
    private const ENVELOPE_SEPARATOR = '|';

    private array $registry;

    public function __construct(array $registry = [])
    {
        $this->registry = $registry ?: [
            'onesignal_app_id' => 'OneSignal app ID (push)',
            'onesignal_rest_api_key' => 'OneSignal REST API key (push)',
            'otp_api_key' => 'OTP gateway API key (SMS)',
            'otp_sender_id' => 'OTP gateway sender ID',
            'otp_template_id' => 'OTP gateway template ID',
            'jwt_secret' => 'Secret key for JWT token signing',
            'encryption_key_bootstrap' => 'Bootstrap encryption key (managed via env)',
        ];
    }

    public function getKey(): string
    {
        $hex = trim((string) getenv('ENCRYPTION_KEY'));

        if ($hex === '' || !ctype_xdigit($hex) || strlen($hex) !== 64) {
            $this->logSecurity('encryption_key_invalid', 'ENCRYPTION_KEY is missing or is not a 64-char hexadecimal 256-bit key');
            throw new SecurityException('Encryption key is missing or invalid');
        }

        return (string) hex2bin($hex);
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->getKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false || $tag === '') {
            throw new SecurityException('Failed to encrypt secret value');
        }

        return base64_encode($iv) . self::ENVELOPE_SEPARATOR
            . base64_encode($tag) . self::ENVELOPE_SEPARATOR
            . base64_encode($ciphertext);
    }

    public function decrypt(string $envelope): string
    {
        $key = $this->getKey();

        $parts = explode(self::ENVELOPE_SEPARATOR, $envelope);
        if (count($parts) !== 3) {
            $this->logSecurity('secret_tamper', 'Secret envelope is malformed (unexpected part count)');
            throw new SecurityException('Stored secret could not be decrypted');
        }

        [$ivB64, $tagB64, $cipherB64] = $parts;

        $iv = base64_decode($ivB64, true);
        $tag = base64_decode($tagB64, true);
        $ciphertext = base64_decode($cipherB64, true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            $this->logSecurity('secret_tamper', 'Secret envelope base64 segments are invalid');
            throw new SecurityException('Stored secret could not be decrypted');
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            $this->logSecurity('secret_tamper', 'Authentication tag verification failed (ciphertext tampered or key mismatch)');
            throw new SecurityException('Stored secret could not be decrypted');
        }

        return $plaintext;
    }

    public function isValidKey(string $key): bool
    {
        return is_string($key)
            && strlen($key) <= 100
            && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key) === 1;
    }

    public function isRegistered(string $key): bool
    {
        return array_key_exists($key, $this->registry);
    }

    public function set(string $key, string $plaintext, ?int $actorId = null): array
    {
        if (!$this->isValidKey($key)) {
            throw new ValidationException(['key' => ['Secret key must be alphanumeric and start with a letter']]);
        }
        if (trim($plaintext) === '') {
            throw new ValidationException(['value' => ['Secret value cannot be empty']]);
        }

        $envelope = $this->encrypt($plaintext);
        $iv = explode(self::ENVELOPE_SEPARATOR, $envelope)[0];

        (new SystemSecret())->upsert($key, $envelope, $iv, 1, $actorId);

        return $this->masked($key, $plaintext);
    }

    public function get(string $key): ?string
    {
        $row = (new SystemSecret())->findByKey($key);
        if ($row === null || (int) $row['is_set'] !== 1) {
            return null;
        }

        return $this->decrypt((string) $row['encrypted_value']);
    }

    public function exists(string $key): bool
    {
        $row = (new SystemSecret())->findByKey($key);
        return $row !== null && (int) $row['is_set'] === 1;
    }

    public function mask(string $plaintext): string
    {
        $length = strlen($plaintext);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($plaintext, -4);
    }

    public function last4(string $plaintext): string
    {
        if (strlen($plaintext) <= 4) {
            return $plaintext;
        }

        return substr($plaintext, -4);
    }

    public function masked(string $key, string $plaintext): array
    {
        return [
            'key' => $key,
            'exists' => true,
            'last4' => $this->last4($plaintext),
            'masked_value' => $this->mask($plaintext),
        ];
    }

    public function list(): array
    {
        $rows = (new SystemSecret())->allRows();
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['key_name']] = $row;
        }

        $result = [];
        $seen = [];

        foreach ($this->registry as $key => $description) {
            $result[] = $this->listEntry($key, $description, $byKey[$key] ?? null);
            $seen[$key] = true;
        }

        foreach ($rows as $row) {
            if (isset($seen[$row['key_name']])) {
                continue;
            }
            $result[] = $this->listEntry($row['key_name'], 'Custom secret', $row);
        }

        usort($result, fn($a, $b) => strcmp($a['key'], $b['key']));

        return $result;
    }

    private function listEntry(string $key, string $description, ?array $row): array
    {
        $entry = [
            'key' => $key,
            'name' => $description,
            'description' => $description,
            'exists' => $row !== null && (int) $row['is_set'] === 1,
            'is_set' => $row !== null && (int) $row['is_set'] === 1,
            'last_updated_at' => $row['updated_at'] ?? null,
            'last_rotated_at' => $row['last_rotated_at'] ?? null,
        ];
        $entry['last4'] = null;
        $entry['masked_value'] = null;

        if ($entry['is_set']) {
            $decrypted = $this->decrypt((string) $row['encrypted_value']);
            $entry['last4'] = $this->last4($decrypted);
            $entry['masked_value'] = $this->mask($decrypted);
        }

        return $entry;
    }

    private function logSecurity(string $type, string $message): void
    {
        $logPath = storage_path('logs/security.log');
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logPath,
            json_encode([
                'timestamp' => gmdate('c'),
                'request_id' => \App\Core\Request::currentRequestId(),
                'level' => 'CRITICAL',
                'type' => $type,
                'message' => $message,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}