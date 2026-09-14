<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;

class JwtService
{
    private string $secret;
    private string $algo = 'HS256';

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (string) (getenv('JWT_SECRET') ?: '');
    }

    public function encode(array $payload): string
    {
        $header = ['alg' => $this->algo, 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthenticationException('TOKEN_INVALID', 'Invalid token', 401);
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $signature = $this->base64UrlDecode($signatureB64);
        $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $this->secret, true);

        if (!hash_equals($expected, $signature)) {
            throw new AuthenticationException('TOKEN_INVALID', 'Invalid token signature', 401);
        }

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== $this->algo) {
            throw new AuthenticationException('TOKEN_INVALID', 'Invalid token header', 401);
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            throw new AuthenticationException('TOKEN_INVALID', 'Invalid token payload', 401);
        }

        return $payload;
    }

    public function validate(string $token): array
    {
        $payload = $this->decode($token);

        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            throw new AuthenticationException('TOKEN_INVALID', 'Missing token expiry', 401);
        }

        if ((int) $payload['exp'] < time()) {
            throw new AuthenticationException('TOKEN_EXPIRED', 'Token has expired', 401);
        }

        return $payload;
    }

    public function validateIgnoringExpiry(string $token): array
    {
        return $this->decode($token);
    }

    public function isExpired(array $payload): bool
    {
        return !isset($payload['exp']) || (int) $payload['exp'] < time();
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
