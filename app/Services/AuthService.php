<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;

class AuthService
{
    private User $userModel;
    private JwtService $jwt;
    private TokenService $tokenService;
    private SessionService $sessions;
    private RememberTokenService $rememberTokens;
    private LoginHistoryService $loginHistory;
    private OtpService $otpService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->jwt = new JwtService();
        $this->tokenService = new TokenService();
        $this->sessions = new SessionService();
        $this->rememberTokens = new RememberTokenService();
        $this->loginHistory = new LoginHistoryService();
        $this->otpService = new OtpService();
    }

    public function login(array $credentials, array $context = []): array
    {
        $identifier = $credentials['mobile'] ?? $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        $user = $this->findByIdentifier($identifier);
        $userId = $user !== null ? (int) $user['id'] : null;

        if ($user === null) {
            $this->loginHistory->recordFailure(null, $identifier, 'ACCOUNT_NOT_FOUND', $context);
            throw new NotFoundException('ACCOUNT_NOT_FOUND', 'Account not found');
        }

        $this->checkLockout($identifier, $context);

        if (!password_verify($password, $user['password_hash'])) {
            $this->loginHistory->recordFailure($userId, (string) $user['mobile'], 'INVALID_PASSWORD', $context);
            $this->maybeLockAccount($user, $identifier, $context);
            throw new AuthenticationException('INVALID_CREDENTIALS', 'Invalid mobile or password', 401);
        }

        $this->checkStatus($user);

        if ((int) ($user['two_factor_enabled'] ?? 0) === 1) {
            return $this->pendingTwoFactor($user, $context);
        }

        return $this->establishSession($user, $credentials, $context);
    }

    public function webLogin(array $credentials, array $context = []): array
    {
        $identifier = $credentials['username'] ?? $credentials['mobile'] ?? '';
        if ($identifier === '') {
            throw new AuthenticationException('INVALID_CREDENTIALS', 'Invalid credentials', 401);
        }

        $user = $this->userModel->byMobileOrUsername($identifier);
        $userId = $user !== null ? (int) $user['id'] : null;

        if ($user === null) {
            $this->loginHistory->recordFailure(null, $identifier, 'ACCOUNT_NOT_FOUND', $context);
            throw new NotFoundException('ACCOUNT_NOT_FOUND', 'Account not found');
        }

        $this->checkLockout($identifier, $context);

        if (!password_verify($credentials['password'] ?? '', $user['password_hash'])) {
            $this->loginHistory->recordFailure($userId, $identifier, 'INVALID_PASSWORD', $context);
            $this->maybeLockAccount($user, $identifier, $context);
            throw new AuthenticationException('INVALID_CREDENTIALS', 'Invalid credentials', 401);
        }

        $role = $user['role'] ?? $this->userModel->roleName((int) $user['role_id']);
        if (strcasecmp((string) $role, 'FARMER') === 0) {
            $this->loginHistory->recordFailure($userId, $identifier, 'INVALID_ROLE', $context);
            throw new AuthenticationException('INVALID_CREDENTIALS', 'This portal is for staff only', 403);
        }

        $this->checkStatus($user);

        if ((int) ($user['two_factor_enabled'] ?? 0) === 1) {
            return $this->pendingTwoFactor($user, $context);
        }

        return $this->establishSession($user, $credentials, $context, true);
    }

    private function pendingTwoFactor(array $user, array $context): array
    {
        $userId = (int) $user['id'];
        $otp = $this->otpService->generate((string) $user['mobile'], 'LOGIN_2FA');

        $this->loginHistory->recordStepUp($userId, (string) $user['mobile'], 'TWO_FA_REQUIRED', $context);

        return [
            'two_factor_required' => true,
            'verification_id' => $otp['verification_id'],
            'resend_after' => $otp['resend_after'],
            'expires_in' => $otp['expires_in'],
        ];
    }

    public function verify2fa(string $verificationId, string $otp, array $credentials, array $context = []): array
    {
        $record = Database::selectOne(
            "SELECT mobile, purpose FROM otp_verifications WHERE verification_id = ? AND purpose = 'LOGIN_2FA' LIMIT 1",
            [$verificationId]
        );
        if ($record === null || (string) $record['purpose'] !== 'LOGIN_2FA') {
            throw new AuthenticationException('INVALID_2FA', 'Invalid verification', 401);
        }

        $user = $this->userModel->byMobile((string) $record['mobile']);
        if ($user === null) {
            throw new NotFoundException('ACCOUNT_NOT_FOUND', 'Account not found');
        }

        $userId = (int) $user['id'];
        $this->check2faRateLimit($userId);

        try {
            $this->otpService->verify($verificationId, $otp);
        } catch (AuthenticationException $e) {
            $this->loginHistory->recordFailure($userId, (string) $user['mobile'], 'INVALID_2FA', $context);
            $this->maybeLockAccount($user, (string) $user['mobile'], $context);
            $expired = $e->getErrorCode() === 'OTP_EXPIRED';
            throw new AuthenticationException($expired ? 'OTP_EXPIRED' : 'INVALID_2FA', $expired ? 'OTP has expired' : 'Invalid 2FA code', $expired ? 400 : 401);
        }

        $this->checkStatus($user);

        return $this->establishSession($user, $credentials, $context);
    }

    public function resend2fa(string $verificationId): array
    {
        return $this->otpService->resend($verificationId);
    }

    public function enable2fa(int $userId): array
    {
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'User not found');
        }

        $otp = $this->otpService->generate((string) $user['mobile'], '2FA_ENABLE');

        return [
            'verification_id' => $otp['verification_id'],
            'resend_after' => $otp['resend_after'],
            'expires_in' => $otp['expires_in'],
        ];
    }

    public function confirmEnable2fa(int $userId, string $verificationId, string $otp): array
    {
        $this->check2faRateLimit($userId);
        $this->verifyOperatorOtp($userId, $verificationId, $otp);

        Database::update(
            'users',
            [
                'two_factor_enabled' => 1,
                'two_factor_enabled_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$userId]
        );

        (new AuditService())->log([
            'user_id' => $userId,
            'action' => 'TWO_FA_ENABLED',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'entity_id' => $userId,
        ]);

        return ['two_factor_enabled' => true];
    }

    public function disable2fa(int $userId, string $verificationId, string $otp): array
    {
        $this->check2faRateLimit($userId);
        $this->verifyOperatorOtp($userId, $verificationId, $otp);

        Database::update(
            'users',
            ['two_factor_enabled' => 0],
            'id = ?',
            [$userId]
        );

        (new AuditService())->log([
            'user_id' => $userId,
            'action' => 'TWO_FA_DISABLED',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'entity_id' => $userId,
        ]);

        return ['two_factor_enabled' => false];
    }

    public function challenge2fa(int $userId): array
    {
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'User not found');
        }

        $otp = $this->otpService->generate((string) $user['mobile'], '2FA_STEP_UP');

        return [
            'verification_id' => $otp['verification_id'],
            'resend_after' => $otp['resend_after'],
            'expires_in' => $otp['expires_in'],
        ];
    }

    public function confirmStepUp(int $userId, string $verificationId, string $otp, string $purpose = '2FA_STEP_UP'): array
    {
        $this->check2faRateLimit($userId);
        $this->verifyOperatorOtp($userId, $verificationId, $otp);

        $claim = $this->issueStepUpClaim($userId, $purpose);

        (new AuditService())->log([
            'user_id' => $userId,
            'action' => 'TWO_FA_STEP_UP',
            'module' => 'AUTH',
            'entity_type' => 'user_step_up',
            'entity_id' => $userId,
            'reason' => $purpose,
        ]);

        return [
            'step_up_claim' => $claim['claim'],
            'expires_in' => $claim['expires_in'],
        ];
    }

    public function verifyStepUpClaim(string $claim, int $userId, string $purpose): bool
    {
        if (!is_string($claim) || !preg_match('/^step_/', $claim)) {
            return false;
        }

        $parts = explode('.', (string) substr($claim, 5));
        if (count($parts) !== 2) {
            return false;
        }

        $secret = (string) (getenv('JWT_SECRET') ?: '');
        $expected = hash_hmac('sha256', $parts[0], $secret);
        if (!hash_equals($expected, $parts[1])) {
            return false;
        }

        $decoded = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/')), true);
        if (!is_array($decoded)) {
            return false;
        }

        return (int) ($decoded['sub'] ?? 0) === $userId
            && (string) ($decoded['purpose'] ?? '') === $purpose
            && (int) ($decoded['exp'] ?? 0) >= time();
    }

    private function verifyOperatorOtp(int $userId, string $verificationId, string $otp): void
    {
        $record = Database::selectOne(
            "SELECT mobile FROM otp_verifications WHERE verification_id = ? LIMIT 1",
            [$verificationId]
        );
        if ($record === null) {
            throw new AuthenticationException('INVALID_2FA', 'Invalid verification', 401);
        }

        $user = $this->userModel->find($userId);
        if ($user === null || (string) $record['mobile'] !== (string) $user['mobile']) {
            throw new AuthenticationException('INVALID_2FA', 'Verification does not match this account', 403);
        }

        try {
            $this->otpService->verify($verificationId, $otp);
        } catch (AuthenticationException $e) {
            $expired = $e->getErrorCode() === 'OTP_EXPIRED';
            throw new AuthenticationException($expired ? 'OTP_EXPIRED' : 'INVALID_2FA', $expired ? 'OTP has expired' : 'Invalid 2FA code', $expired ? 400 : 401);
        }
    }

    private function issueStepUpClaim(int $userId, string $purpose): array
    {
        $expirySeconds = max(60, (int) get_setting('step_up_expiry_seconds', 600));
        $secret = (string) (getenv('JWT_SECRET') ?: '');

        $payload = base64_encode(json_encode([
            'sub' => $userId,
            'purpose' => $purpose,
            'iat' => time(),
            'exp' => time() + $expirySeconds,
        ], JSON_UNESCAPED_UNICODE));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');

        $sig = hash_hmac('sha256', $payload, $secret);

        return [
            'claim' => 'step_' . $payload . '.' . $sig,
            'expires_in' => $expirySeconds,
        ];
    }

    private function check2faRateLimit(int $userId): void
    {
        $limiter = new RateLimiter();
        $result = $limiter->check('2fa:' . $userId, 3, 300);

        if ($result['exceeded']) {
            throw new RateLimitException((int) $result['retry_after'], 'Too many 2FA attempts. Please try again later.');
        }

        $limiter->increment('2fa:' . $userId, 300);
    }

    public function refresh(string $refreshToken, array $context = []): array
    {
        $session = $this->sessions->findByRefreshToken($refreshToken);

        if ($session === null) {
            throw new AuthenticationException('TOKEN_INVALID', 'Invalid refresh token', 401);
        }

        try {
            $this->sessions->validateActive($session);
            $this->sessions->validateUserActive((int) $session['user_id']);
        } catch (AuthenticationException $e) {
            if ($e->getErrorCode() === 'TOKEN_EXPIRED') {
                throw new AuthenticationException('TOKEN_EXPIRED', 'Token has expired. Please login again.', 400);
            }
            throw $e;
        }

        $user = $this->userModel->find((int) $session['user_id']);
        if ($user === null) {
            throw new AuthenticationException('TOKEN_INVALID', 'User not found', 401);
        }

        $rotated = $this->sessions->rotateRefreshToken($session, (int) $user['id'], $context);

        $accessToken = $this->createAccessToken($user, (int) $session['id']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $rotated['refresh_token'],
            'token_type' => 'bearer',
            'expires_in' => (int) (getenv('JWT_ACCESS_EXPIRY') ?: 900),
        ];
    }

    public function logout(string $refreshToken, array $context = []): void
    {
        $session = $this->sessions->findByRefreshToken($refreshToken);

        if ($session === null) {
            throw new AuthenticationException('TOKEN_INVALID', 'Invalid refresh token', 401);
        }

        $this->rememberTokens->revokeForSession($session);
        $this->sessions->markLoggedOut((int) $session['id']);
        $this->loginHistory->recordLogout((int) $session['id']);
    }

    public function logoutBySessionId(int $sessionId): void
    {
        $this->rememberTokens->revokeForSession(['remember' => $this->sessionRememberId($sessionId)]);
        $this->sessions->markLoggedOut($sessionId);
        $this->loginHistory->recordLogout($sessionId);
    }

    private function sessionRememberId(int $sessionId): ?int
    {
        $row = Database::selectOne("SELECT remember FROM user_sessions WHERE id = ?", [$sessionId]);
        return $row != null && $row['remember'] !== null ? (int) $row['remember'] : null;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword, ?int $currentSessionId = null, array $context = []): void
    {
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'User not found');
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new AuthenticationException('INVALID_CREDENTIALS', 'Current password is incorrect', 401);
        }

        $this->updatePassword($user, $newPassword, $userId, true, $currentSessionId);
    }

    public function resetPassword(int $userId, string $newPassword): void
    {
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'User not found');
        }

        $this->updatePassword($user, $newPassword, $userId, false);
    }

    private function updatePassword(array $user, string $newPassword, int $userId, bool $keepCurrent, ?int $currentSessionId = null): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        Database::update(
            'users',
            [
                'password_hash' => $hash,
                'password_set_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$userId]
        );

        if (!$keepCurrent) {
            $this->sessions->revokeAllForUser($userId, null, 'password_reset');
            $this->rememberTokens->revokeAllForUser($userId);
        } else {
            $this->sessions->revokeAllForUser($userId, $userId, 'password_change');
            if ($currentSessionId !== null) {
                Database::update(
                    'user_sessions',
                    ['status' => 'ACTIVE', 'revoked_at' => null, 'revoked_reason' => null],
                    'id = ? AND user_id = ?',
                    [$currentSessionId, $userId]
                );
            }
            $this->rememberTokens->revokeAllForUser($userId);
        }
    }

    public function authenticateAccessToken(string $token): array
    {
        $payload = $this->jwt->validate($token);

        if (!isset($payload['sub'], $payload['session_id'])) {
            throw new AuthenticationException('TOKEN_INVALID', 'Invalid token claims', 401);
        }

        $session = Database::selectOne(
            "SELECT * FROM user_sessions WHERE id = ? AND user_id = ?",
            [(int) $payload['session_id'], (int) $payload['sub']]
        );

        if ($session === null) {
            throw new AuthenticationException('TOKEN_INVALID', 'Session not found', 401);
        }

        $this->sessions->validateActive($session);
        $this->sessions->validateUserActive((int) $payload['sub']);

        $user = $this->userModel->find((int) $payload['sub']);
        if ($user === null) {
            throw new AuthenticationException('TOKEN_INVALID', 'User not found', 401);
        }

        return [
            'user' => $user,
            'session' => $session,
            'payload' => $payload,
        ];
    }

    public function createSessionContext(\App\Core\Request $request): array
    {
        return [
            'device_id' => $request->input('device_id') ?: $request->header('x-device-id'),
            'device_name' => $request->input('device_name'),
            'platform' => $request->input('platform') ?: $request->header('x-platform'),
            'app_version' => $request->input('app_version') ?: $request->header('x-app-version'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    public function getAccessExpiry(): int
    {
        return (int) (getenv('JWT_ACCESS_EXPIRY') ?: 900);
    }

    public function roleOf(int $roleId): string
    {
        return $this->userModel->roleName($roleId);
    }

    private function establishSession(array $user, array $credentials, array $context, bool $web = false): array
    {
        $userId = (int) $user['id'];

        $rememberTokenId = null;
        $rememberRaw = null;
        if (!empty($credentials['remember_me'])) {
            $remember = $this->rememberTokens->create($userId, $context);
            $rememberTokenId = $remember['id'];
            $rememberRaw = $remember['token'];
        }

        $session = $this->sessions->create($userId, array_merge($context, ['type' => $web ? 'web' : 'app']), $rememberTokenId);
        $sessionId = (int) $session['session_id'];

        $this->registerDevice($user, $context);

        $userWithRole = $this->userModel->withRole($user);

        $loginHistory = new LoginHistoryService();
        $loginHistory->recordSuccess($userId, (string) $user['mobile'], $sessionId, $context);

        $this->userModel->updateLastLogin($userId);

        $result = [
            'access_token' => $this->createAccessToken($user, $sessionId),
            'refresh_token' => $session['refresh_token'],
            'token_type' => 'bearer',
            'expires_in' => $this->getAccessExpiry(),
            'user' => $this->presentUser($userWithRole, $userId),
            'permissions' => $this->userModel->permissionNames($userId),
        ];

        if ($rememberRaw !== null) {
            $result['remember_token'] = $rememberRaw;
        }

        return $result;
    }

    private function registerDevice(array $user, array $context): void
    {
        $deviceId = $context['device_id'] ?? null;
        if ($deviceId === null) {
            return;
        }

        $userId = (int) $user['id'];
        $deviceModel = new UserDevice();
        $existing = $deviceModel->byUserDevice($userId, $deviceId);

        if ($existing !== null) {
            Database::update(
                'user_devices',
                [
                    'device_name' => $context['device_name'] ?? $existing['device_name'],
                    'platform' => $context['platform'] ?? $existing['platform'],
                    'app_version' => $context['app_version'] ?? $existing['app_version'],
                    'onesignal_player_id' => $context['onesignal_player_id'] ?? $existing['onesignal_player_id'],
                    'last_ip_address' => $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                    'last_seen_at' => date('Y-m-d H:i:s'),
                    'is_current' => 1,
                ],
                'id = ?',
                [(int) $existing['id']]
            );
            Database::query("UPDATE user_devices SET is_current = 0 WHERE user_id = ? AND id != ?", [$userId, (int) $existing['id']]);
        } else {
            $deviceModel->insert([
                'user_id' => $userId,
                'device_id' => $deviceId,
                'device_name' => $context['device_name'] ?? null,
                'platform' => $context['platform'] ?? null,
                'app_version' => $context['app_version'] ?? null,
                'onesignal_player_id' => $context['onesignal_player_id'] ?? null,
                'last_ip_address' => $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'last_seen_at' => date('Y-m-d H:i:s'),
                'is_current' => 1,
            ]);
            Database::query("UPDATE user_devices SET is_current = 0 WHERE user_id = ? AND id != LAST_INSERT_ID()", [$userId]);
        }
    }

    public function registerDeviceForUser(int $userId, array $data, ?string $ip = null): void
    {
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'User not found');
        }

        $context = [
            'device_id' => $data['device_id'] ?? null,
            'device_name' => $data['device_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'onesignal_player_id' => $data['onesignal_player_id'] ?? null,
            'ip_address' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        ];

        $this->registerDevice($user, $context);
    }

    public function createAccessToken(array $user, int $sessionId): string
    {
        $expiry = $this->getAccessExpiry();
        $payload = [
            'sub' => (int) $user['id'],
            'role' => $user['role'] ?? $this->userModel->roleName((int) $user['role_id']),
            'session_id' => $sessionId,
            'iat' => time(),
            'exp' => time() + $expiry,
            'jti' => $this->tokenService->generateJti(),
        ];
        return $this->jwt->encode($payload);
    }

    public function presentUser(array $user, int $userId): array
    {
        $data = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'mobile' => $user['mobile'] ?? null,
            'email' => $user['email'] ?? null,
            'username' => $user['username'] ?? null,
            'role' => $user['role'] ?? '',
            'role_id' => (int) ($user['role_id'] ?? 0),
            'status' => $user['status'],
            'verification_status' => $user['verification_status'] ?? 'APPROVED',
            'is_super_admin' => (int) ($user['is_super_admin'] ?? 0),
            'two_factor_enabled' => (int) ($user['two_factor_enabled'] ?? 0) === 1,
        ];

        $farmer = Database::selectOne(
            "SELECT * FROM farmers WHERE user_id = ?",
            [$userId]
        );

        if ($farmer !== null) {
            $data['farmer'] = [
                'id' => (int) $farmer['id'],
                'verification_status' => $farmer['verification_status'],
                'village' => $farmer['village'],
                'district_id' => (int) $farmer['district_id'],
                'state' => $farmer['state'],
            ];
        }

        return $data;
    }

    private function findByIdentifier(string $identifier): ?array
    {
        if (str_starts_with($identifier, '+') || preg_match('/^\d{10,15}$/', $identifier)) {
            $normalized = preg_replace('/^\+?91/', '', $identifier);
            return $this->userModel->byMobile($normalized) ?? $this->userModel->byMobile($identifier);
        }
        return $this->userModel->byMobileOrUsername($identifier);
    }

    private function checkStatus(array $user): void
    {
        $status = $user['status'];
        $verification = $user['verification_status'] ?? null;

        if ($status === 'LOCKED') {
            throw new AuthenticationException('ACCOUNT_LOCKED', 'Account is locked due to multiple failed attempts', 403);
        }

        if ($verification === 'PENDING') {
            throw new AuthenticationException('ACCOUNT_PENDING', 'Your account verification is pending', 403);
        }

        if ($verification === 'REJECTED') {
            throw new AuthenticationException('ACCOUNT_REJECTED', 'Your account verification was rejected', 403);
        }

        if ($status === 'INACTIVE') {
            throw new AuthenticationException('ACCOUNT_REJECTED', 'Your account is inactive', 403);
        }
    }

    private function checkLockout(string $identifier, array $context): void
    {
        $maxAttempts = (int) get_setting('max_login_attempts', 5);
        $windowMinutes = (int) get_setting('lockout_minutes', 15);

        $count = Database::selectOne(
            "SELECT COUNT(*) AS total FROM login_history
             WHERE mobile = ? AND status = 'FAILURE'
             AND login_at >= ?",
            [$identifier, date('Y-m-d H:i:s', time() - ($windowMinutes * 60))]
        );

        if ($count !== null && (int) $count['total'] >= $maxAttempts) {
            $this->loginHistory->recordFailure(null, $identifier, 'ACCOUNT_LOCKED', $context);
            throw new AuthenticationException('ACCOUNT_LOCKED', 'Account is temporarily locked due to multiple failed attempts', 429);
        }
    }

    private function maybeLockAccount(array $user, string $identifier, array $context): void
    {
        $maxAttempts = (int) get_setting('max_login_attempts', 5);
        $windowMinutes = (int) get_setting('lockout_minutes', 15);

        $count = Database::selectOne(
            "SELECT COUNT(*) AS total FROM login_history
             WHERE mobile = ? AND status = 'FAILURE'
             AND login_at >= ?",
            [$identifier, date('Y-m-d H:i:s', time() - ($windowMinutes * 60))]
        );

        if ($count !== null && (int) $count['total'] >= $maxAttempts) {
            Database::update('users', ['status' => 'LOCKED'], 'id = ?', [(int) $user['id']]);
            $this->loginHistory->recordFailure((int) $user['id'], $identifier, 'ACCOUNT_LOCKED', $context);
        }
    }
}
