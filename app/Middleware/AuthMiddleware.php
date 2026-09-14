<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RbacService;
use App\Services\SessionService;
use App\Services\TokenService;

class AuthMiddleware implements MiddlewareInterface
{
    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    public function handle(Request $request, callable $next): void
    {
        try {
            $user = $this->resolveUser($request);
            if ($user !== null) {
                $request->setUser($user);
            }
        } catch (AuthenticationException $e) {
            if ($e->getErrorCode() === 'TOKEN_EXPIRED') {
                $this->respondExpired($request);
                return;
            }
            $this->respondUnauthorized($request, $e);
            return;
        }

        $next($request);
    }

    private function resolveUser(Request $request): ?array
    {
        $token = $request->getBearerToken();
        if ($token !== null) {
            return $this->resolveJwt($token);
        }

        if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'] !== '') {
            return $this->resolveWebSession();
        }

        return null;
    }

    private function resolveJwt(string $token): array
    {
        $authService = new AuthService();
        $result = $authService->authenticateAccessToken($token);

        $user = $result['user'];
        $session = $result['session'];
        $roleName = $user['role'] ?? $authService->roleOf((int) $user['role_id']);

        return $this->buildUserContext($user, $roleName, (int) $session['id'], $user['status'] ?? '', 'jwt');
    }

    private function resolveWebSession(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->configureSessionIni();
            session_start();
        }

        $rawToken = $_SESSION['session_token'] ?? null;
        if ($rawToken === null || !is_string($rawToken) || $rawToken === '') {
            return null;
        }

        $sessionService = new SessionService();
        $session = $sessionService->findBySessionToken($rawToken);
        if ($session === null) {
            $this->clearWebSession();
            return null;
        }

        try {
            $sessionService->validateActive($session);
            $sessionService->validateUserActive((int) $session['user_id']);
        } catch (AuthenticationException $e) {
            $this->clearWebSession();
            $this->recordInvalidSession($e->getErrorCode());
            throw $e;
        }

        $user = Database::selectOne("SELECT * FROM users WHERE id = ?", [(int) $session['user_id']]);
        if ($user === null) {
            $this->clearWebSession();
            return null;
        }

        if ((int) $session['last_activity_at'] === 0 || time() - strtotime((string) $session['last_activity_at']) >= 60) {
            $sessionService->updateLastActivity((int) $session['id']);
        }

        $roleName = (new User())->roleName((int) $user['role_id']);

        return $this->buildUserContext($user, $roleName, (int) $session['id'], $user['status'] ?? '', 'session');
    }

    private function buildUserContext(array $user, string $roleName, int $sessionId, string $status, string $method): array
    {
        $userId = (int) $user['id'];

        $contextCandidate = [
            'id' => $userId,
            'role_id' => (int) ($user['role_id'] ?? 0),
            'role' => $roleName,
            'is_super_admin' => (int) ($user['is_super_admin'] ?? 0),
        ];

        $permissions = (new RbacService())->effectivePermissions($userId, $contextCandidate);

        return [
            'id' => $userId,
            'name' => $user['name'],
            'mobile' => $user['mobile'] ?? null,
            'email' => $user['email'] ?? null,
            'username' => $user['username'] ?? null,
            'role_id' => (int) ($user['role_id'] ?? 0),
            'role' => $roleName,
            'is_super_admin' => (int) ($user['is_super_admin'] ?? 0),
            'status' => $status,
            'verification_status' => $user['verification_status'] ?? 'APPROVED',
            'permissions' => $permissions,
            'session_id' => $sessionId,
            'auth_method' => $method,
        ];
    }

    private function recordInvalidSession(string $reason): void
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
                'request_id' => Request::currentRequestId(),
                'level' => 'WARN',
                'type' => 'invalid_web_session',
                'message' => 'Web session rejected: ' . $reason,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function clearWebSession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    private function configureSessionIni(): void
    {
        $secure = getenv('APP_ENV') === 'production';
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_secure', $secure ? '1' : '0');
        @ini_set('session.cookie_samesite', 'Lax');
        @ini_set('session.use_strict_mode', '1');
    }

    private function respondExpired(Request $request): void
    {
        $this->logAuthReject('TOKEN_EXPIRED', $request);
        http_response_code(401);
        Response::error('TOKEN_EXPIRED', 'Token has expired', 401);
        header('WWW-Authenticate: Bearer error="invalid_token", error_description="The access token expired"');
    }

    private function respondUnauthorized(Request $request, AuthenticationException $e): void
    {
        $this->logAuthReject($e->getErrorCode(), $request);
        Response::error($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus() ?: 401, $e->getDetails());
    }

    private function logAuthReject(string $code, Request $request): void
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
                'request_id' => Request::currentRequestId(),
                'level' => 'WARN',
                'type' => 'auth_reject',
                'code' => $code,
                'method' => $request->method(),
                'endpoint' => $request->uri(),
                'ip' => $request->ip(),
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}