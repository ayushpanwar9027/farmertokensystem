<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthenticationException;
use App\Models\RememberToken;

class RememberTokenService
{
    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    public function create(int $userId, array $context = []): array
    {
        $raw = $this->tokenService->generateRememberToken();
        $hash = $this->tokenService->hash($raw);

        $expiryDays = (int) get_setting('remember_me_expiry_days', 30);

        $id = (new RememberToken())->insert([
            'user_id' => $userId,
            'token_hash' => $hash,
            'device_id' => $context['device_id'] ?? null,
            'device_name' => $context['device_name'] ?? null,
            'platform' => $context['platform'] ?? null,
            'ip_address' => $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
            'last_used_at' => null,
            'expires_at' => date('Y-m-d H:i:s', time() + ($expiryDays * 86400)),
            'status' => 'ACTIVE',
        ]);

        return ['id' => $id, 'token' => $raw, 'hash' => $hash, 'expiry_days' => $expiryDays];
    }

    public function findByToken(string $rawToken): ?array
    {
        $hash = $this->tokenService->hash($rawToken);
        return (new RememberToken())->byTokenHash($hash);
    }

    public function validateActive(array $remember): void
    {
        if (($remember['status'] ?? '') !== 'ACTIVE') {
            throw new AuthenticationException('TOKEN_REVOKED', 'Remember token has been revoked', 401);
        }

        if (strtotime((string) $remember['expires_at']) < time()) {
            throw new AuthenticationException('TOKEN_EXPIRED', 'Remember token has expired', 401);
        }
    }

    public function renew(array $remember): void
    {
        Database::update(
            'remember_tokens',
            [
                'last_used_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + ((int) get_setting('remember_me_expiry_days', 30) * 86400)),
            ],
            'id = ?',
            [(int) $remember['id']]
        );
    }

    public function revoke(int $id, ?int $revokedBy = null): void
    {
        Database::update(
            'remember_tokens',
            [
                'status' => 'REVOKED',
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoked_by' => $revokedBy,
            ],
            'id = ?',
            [$id]
        );
    }

    public function revokeForSession(array $session): void
    {
        $rememberId = $session['remember'] ?? null;
        if ($rememberId !== null) {
            $this->revoke((int) $rememberId);
        }
    }

    public function revokeAllForUser(int $userId, ?int $revokedBy = null): void
    {
        Database::update(
            'remember_tokens',
            [
                'status' => 'REVOKED',
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoked_by' => $revokedBy,
            ],
            'user_id = ? AND status = \'ACTIVE\'',
            [$userId]
        );
    }
}
