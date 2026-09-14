<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthenticationException;
use App\Models\UserSession;

class SessionService
{
    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    public function create(int $userId, array $context = [], ?int $rememberTokenId = null): array
    {
        $this->enforceMaxConcurrent($userId);

        $refreshToken = $this->tokenService->generateRefreshToken();
        $hash = $this->tokenService->hash($refreshToken);

        $sessionTimeoutMinutes = (int) get_setting('session_timeout_minutes', 30);
        if (($context['type'] ?? 'app') === 'app') {
            $expiry = (int) getenv('JWT_REFRESH_EXPIRY') ?: 604800;
        } else {
            $expiry = $sessionTimeoutMinutes * 60;
        }

        $sessionId = (new UserSession())->insert([
            'user_id' => $userId,
            'session_token_hash' => $hash,
            'device_id' => $context['device_id'] ?? null,
            'device_name' => $context['device_name'] ?? null,
            'platform' => $context['platform'] ?? null,
            'app_version' => $context['app_version'] ?? null,
            'ip_address' => $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'remember' => $rememberTokenId,
            'is_remembered' => $rememberTokenId !== null ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + $expiry),
            'status' => 'ACTIVE',
        ]);

        return [
            'session_id' => $sessionId,
            'refresh_token' => $refreshToken,
            'session_token_hash' => $hash,
            'expiry_seconds' => $expiry,
        ];
    }

    public function findByRefreshToken(string $refreshToken): ?array
    {
        $hash = $this->tokenService->hash($refreshToken);
        return (new UserSession())->byTokenHash($hash);
    }

    public function findBySessionToken(string $rawToken): ?array
    {
        $hash = $this->tokenService->hash($rawToken);
        return (new UserSession())->byTokenHash($hash);
    }

    public function validateActive(array $session): void
    {
        if (($session['status'] ?? '') !== 'ACTIVE') {
            throw new AuthenticationException('TOKEN_REVOKED', 'Session has been revoked', 401);
        }

        if (strtotime((string) $session['expires_at']) < time()) {
            throw new AuthenticationException('TOKEN_EXPIRED', 'Session has expired', 401);
        }
    }

    public function validateUserActive(int $userId): void
    {
        $user = Database::selectOne("SELECT status FROM users WHERE id = ?", [$userId]);
        if ($user === null) {
            throw new AuthenticationException('TOKEN_INVALID', 'User no longer exists', 401);
        }
        if ($user['status'] === 'LOCKED') {
            throw new AuthenticationException('ACCOUNT_LOCKED', 'Account is locked', 403);
        }
        if ($user['status'] === 'INACTIVE') {
            throw new AuthenticationException('ACCOUNT_REJECTED', 'Account is inactive', 403);
        }
    }

    public function rotateRefreshToken(array $session, int $userId, array $context = []): array
    {
        $newRefresh = $this->tokenService->generateRefreshToken();
        $newHash = $this->tokenService->hash($newRefresh);

        Database::update(
            'user_sessions',
            [
                'session_token_hash' => $newHash,
                'last_activity_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + ((int) getenv('JWT_REFRESH_EXPIRY') ?: 604800)),
            ],
            'id = ?',
            [(int) $session['id']]
        );

        return [
            'session_id' => (int) $session['id'],
            'refresh_token' => $newRefresh,
            'session_token_hash' => $newHash,
        ];
    }

    public function updateLastActivity(int $sessionId): void
    {
        Database::query(
            "UPDATE user_sessions SET last_activity_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $sessionId]
        );
    }

    public function revoke(int $sessionId, ?int $revokedBy = null, string $reason = ''): void
    {
        Database::update(
            'user_sessions',
            [
                'status' => 'REVOKED',
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoked_by' => $revokedBy,
                'revoked_reason' => $reason ?: null,
            ],
            'id = ?',
            [$sessionId]
        );
    }

    public function markLoggedOut(int $sessionId): void
    {
        Database::update(
            'user_sessions',
            ['status' => 'LOGGED_OUT'],
            'id = ?',
            [$sessionId]
        );
    }

    public function activeSessions(int $userId): array
    {
        return Database::select(
            "SELECT * FROM user_sessions WHERE user_id = ? AND status = 'ACTIVE' ORDER BY created_at ASC",
            [$userId]
        );
    }

    public function revokeAllOthers(int $userId, int $exceptSessionId, ?int $revokedBy = null): int
    {
        $updated = Database::update(
            'user_sessions',
            [
                'status' => 'REVOKED',
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoked_by' => $revokedBy,
                'revoked_reason' => 'revoke_all',
            ],
            'user_id = ? AND status = \'ACTIVE\' AND id != ?',
            [$userId, $exceptSessionId]
        );
        return $updated;
    }

    public function revokeAllForUser(int $userId, ?int $revokedBy = null, string $reason = ''): void
    {
        Database::update(
            'user_sessions',
            [
                'status' => 'REVOKED',
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoked_by' => $revokedBy,
                'revoked_reason' => $reason ?: null,
            ],
            'user_id = ? AND status = \'ACTIVE\'',
            [$userId]
        );
    }

    public function expirePastSessions(): int
    {
        $updated = Database::update(
            'user_sessions',
            ['status' => 'EXPIRED'],
            'status = \'ACTIVE\' AND expires_at <= NOW()'
        );
        return $updated;
    }

    private function enforceMaxConcurrent(int $userId): void
    {
        $max = (int) get_setting('max_concurrent_sessions', 10);
        if ($max <= 0) {
            return;
        }

        $active = $this->activeSessions($userId);
        if (count($active) < $max) {
            return;
        }

        $toEvict = count($active) - $max + 1;
        for ($i = 0; $i < $toEvict; $i++) {
            $this->revoke((int) $active[$i]['id'], null, 'max_concurrent_eviction');
        }
    }
}
