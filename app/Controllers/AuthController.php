<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\FarmerAuthService;
use App\Services\LoginHistoryService;
use App\Services\OtpService;
use App\Services\PasswordResetService;
use App\Services\RememberTokenService;
use App\Services\SessionService;
use App\Validators\AuthValidator;

class AuthController
{
    private AuthValidator $validator;
    private AuthService $authService;
    private AuditService $audit;

    public function __construct()
    {
        $this->validator = new AuthValidator();
        $this->authService = new AuthService();
        $this->audit = new AuditService();
    }

    public function register(Request $request): void
    {
        $data = $this->validator->register($request->all());

        $farmerAuth = new FarmerAuthService();
        $result = $farmerAuth->register($data['mobile']);

        $this->audit->log([
            'action' => 'REGISTER_INITIATED',
            'module' => 'AUTH',
            'entity_type' => 'otp_verification',
            'new_value' => ['mobile' => $data['mobile']],
        ]);

        Response::success($result);
    }

    public function webCsrf(Request $request): void
    {
        // Prime/refresh the HttpOnly session cookie alongside the JS-readable
        // CSRF token cookie so anonymous csrf requests also carry a properly
        // flagged session cookie (session fixation defence + scan compliance).
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_secure', getenv('APP_ENV') === 'production' ? '1' : '0');
            @ini_set('session.cookie_samesite', 'Lax');
            @ini_set('session.use_strict_mode', '1');
            session_start();
        }

        $token = $_COOKIE['csrf_token'] ?? '';
        if ($token === '') {
            Response::error('CSRF_NOT_READY', 'CSRF token not primed', 425);
            return;
        }
        Response::success(['csrf_token' => $token]);
    }

    public function verifyOtp(Request $request): void
    {
        $data = $this->validator->verifyOtp($request->all());

        $farmerAuth = new FarmerAuthService();
        $result = $farmerAuth->verifyOtp($data['verification_id'], $data['otp']);

        $this->audit->log([
            'action' => 'OTP_VERIFIED',
            'module' => 'AUTH',
            'entity_type' => 'otp_verification',
            'new_value' => ['verification_id' => $data['verification_id']],
        ]);

        Response::success($result);
    }

    public function resendOtp(Request $request): void
    {
        $data = $this->validator->resendOtp($request->all());

        $otpService = new OtpService();
        $result = $otpService->resend($data['verification_id']);

        Response::success($result);
    }

    public function completeRegistration(Request $request): void
    {
        $data = $this->validator->completeRegistration($request->all());

        $farmerAuth = new FarmerAuthService();
        $result = $farmerAuth->completeRegistration($data);

        $this->audit->log([
            'action' => 'REGISTER_COMPLETED',
            'module' => 'AUTH',
            'entity_type' => 'farmer',
            'new_value' => ['farmer_id' => $result['farmer']['id'], 'mobile' => $result['farmer']['mobile']],
        ]);

        Response::created($result);
    }

    public function login(Request $request): void
    {
        $data = $this->validator->appLogin($request->all());
        $context = $this->authService->createSessionContext($request);

        $result = $this->authService->login($data, $context);

        if (!empty($result['two_factor_required'])) {
            Response::json(
                $result,
                202,
                ['code' => 'TWO_FA_REQUIRED']
            );
            return;
        }

        $this->audit->log([
            'user_id' => $result['user']['id'],
            'user_name' => $result['user']['name'],
            'user_role' => $result['user']['role'],
            'action' => 'LOGIN',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'entity_id' => $result['user']['id'],
        ]);

        Response::success($result);
    }

    public function verify2fa(Request $request): void
    {
        $data = $this->validator->verify2fa($request->all());
        $context = $this->authService->createSessionContext($request);

        $result = $this->authService->verify2fa($data['verification_id'], $data['otp'], $data, $context);

        $this->audit->log([
            'user_id' => $result['user']['id'],
            'user_name' => $result['user']['name'],
            'user_role' => $result['user']['role'],
            'action' => 'LOGIN_2FA',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'entity_id' => $result['user']['id'],
        ]);

        Response::success($result);
    }

    public function resend2fa(Request $request): void
    {
        $data = $this->validator->resend2fa($request->all());

        $result = $this->authService->resend2fa($data['verification_id']);

        Response::success($result);
    }

    public function enable2fa(Request $request): void
    {
        $user = $this->requireUser($request);

        $result = $this->authService->enable2fa((int) $user['id']);

        Response::success($result);
    }

    public function confirmEnable2fa(Request $request): void
    {
        $user = $this->requireUser($request);
        $data = $this->validator->confirm2fa($request->all());

        $result = $this->authService->confirmEnable2fa((int) $user['id'], $data['verification_id'], $data['otp']);

        Response::success($result);
    }

    public function disable2fa(Request $request): void
    {
        $user = $this->requireUser($request);
        $data = $this->validator->confirm2fa($request->all());

        $result = $this->authService->disable2fa((int) $user['id'], $data['verification_id'], $data['otp']);

        Response::success($result);
    }

    public function challenge2fa(Request $request): void
    {
        $user = $this->requireUser($request);

        $result = $this->authService->challenge2fa((int) $user['id']);

        Response::success($result);
    }

    public function confirmStepUp(Request $request): void
    {
        $user = $this->requireUser($request);
        $data = $this->validator->confirm2fa($request->all());

        $result = $this->authService->confirmStepUp(
            (int) $user['id'],
            $data['verification_id'],
            $data['otp'],
            (string) ($data['purpose'] ?? '2FA_STEP_UP')
        );

        Response::success($result);
    }

    public function webVerify2fa(Request $request): void
    {
        $data = $this->validator->verify2fa($request->all());
        $context = $this->authService->createSessionContext($request);

        $result = $this->authService->verify2fa($data['verification_id'], $data['otp'], $data, $context);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_secure', getenv('APP_ENV') === 'production' ? '1' : '0');
            @ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['session_token'] = $result['refresh_token'];
        $_SESSION['user_id'] = $result['user']['id'];

        $this->setCsrfCookie();
        $this->setSessionCookieLifetime((int) get_setting('session_timeout_minutes', 30) * 60);

        Response::success(
            ['user' => $result['user'], 'permissions' => $result['permissions']],
            ['session_lifetime' => (int) get_setting('session_timeout_minutes', 30) * 60]
        );
    }

    public function logout(Request $request): void
    {
        $data = $this->validator->logout($request->all());

        $refreshToken = $data['refresh_token'] ?? null;
        $userId = $request->getUserId();
        $userName = $request->getUser()['name'] ?? '';
        $roleName = $request->getUser()['role'] ?? '';

        if ($refreshToken !== null && $refreshToken !== '') {
            $this->authService->logout($refreshToken);
        } elseif ($userId !== null) {
            $sessionId = $request->getUser()['session_id'] ?? null;
            if ($sessionId !== null) {
                $this->authService->logoutBySessionId((int) $sessionId);
            }
        }

        $this->audit->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'user_role' => $roleName,
            'action' => 'LOGOUT',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'entity_id' => $userId,
        ]);

        Response::success(['message' => 'Logged out']);
    }

    public function refresh(Request $request): void
    {
        $data = $this->validator->refresh($request->all());

        $result = $this->authService->refresh($data['refresh_token'], $this->authService->createSessionContext($request));

        Response::success($result);
    }

    public function me(Request $request): void
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthenticationException('UNAUTHENTICATED', 'Authentication required', 401);
        }

        $authService = new AuthService();
        $dbUser = (new \App\Models\User())->find((int) $user['id']);
        if ($dbUser === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'User not found');
        }
        $dbUser['role'] = (new \App\Models\User())->roleName((int) $dbUser['role_id']);
        $profile = $authService->presentUser($dbUser, (int) $dbUser['id']);

        Response::success($profile);
    }

    public function forgotPassword(Request $request): void
    {
        $data = $this->validator->forgotPassword($request->all());

        $service = new PasswordResetService();
        $result = $service->forgot($data['mobile']);

        $this->audit->log([
            'action' => 'PASSWORD_RESET_REQUESTED',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'new_value' => ['mobile' => $data['mobile']],
        ]);

        Response::success($result);
    }

    public function resetPassword(Request $request): void
    {
        $data = $this->validator->resetPassword($request->all());

        $service = new PasswordResetService();
        $service->reset($data['reset_id'], $data['otp'], $data['password']);

        $this->audit->log([
            'action' => 'PASSWORD_RESET',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'new_value' => ['reset_id' => $data['reset_id']],
        ]);

        Response::success(['message' => 'Password reset successful']);
    }

    public function webLogin(Request $request): void
    {
        $data = $this->validator->webLogin($request->all());
        $data['username'] = $data['username'] ?? $data['mobile'] ?? null;

        $context = $this->authService->createSessionContext($request);
        $result = $this->authService->webLogin($data, $context);

        if (!empty($result['two_factor_required'])) {
            Response::json(
                $result,
                202,
                ['code' => 'TWO_FA_REQUIRED']
            );
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_secure', getenv('APP_ENV') === 'production' ? '1' : '0');
            @ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['session_token'] = $result['refresh_token'];
        $_SESSION['user_id'] = $result['user']['id'];

        $this->setCsrfCookie();
        $this->setSessionCookieLifetime((int) get_setting('session_timeout_minutes', 30) * 60);

        $this->audit->log([
            'user_id' => $result['user']['id'],
            'user_name' => $result['user']['name'],
            'user_role' => $result['user']['role'],
            'action' => 'LOGIN',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'entity_id' => $result['user']['id'],
        ]);

        Response::success(
            ['user' => $result['user'], 'permissions' => $result['permissions']],
            ['session_lifetime' => (int) get_setting('session_timeout_minutes', 30) * 60]
        );
    }

    public function webLogout(Request $request): void
    {
        $userId = $request->getUserId();
        $sessionId = $request->getUser()['session_id'] ?? null;

        if ($sessionId !== null) {
            $row = Database::selectOne("SELECT remember FROM user_sessions WHERE id = ?", [(int) $sessionId]);
            if ($row !== null && $row['remember'] !== null) {
                (new RememberTokenService())->revoke((int) $row['remember']);
            }
            $sessionService = new SessionService();
            $sessionService->markLoggedOut((int) $sessionId);
            (new LoginHistoryService())->recordLogout((int) $sessionId);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_secure', getenv('APP_ENV') === 'production' ? '1' : '0');
            @ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }

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

        $this->audit->log([
            'user_id' => $userId,
            'action' => 'LOGOUT',
            'module' => 'AUTH',
            'entity_type' => 'user',
            'entity_id' => $userId,
        ]);

        Response::success(['message' => 'Logged out']);
    }

    public function webMe(Request $request): void
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthenticationException('UNAUTHENTICATED', 'Authentication required', 401);
        }

        $dbUser = (new \App\Models\User())->find((int) $user['id']);
        if ($dbUser === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'User not found');
        }
        $dbUser['role'] = (new \App\Models\User())->roleName((int) $dbUser['role_id']);

        Response::success([
            'user' => $this->authService->presentUser($dbUser, (int) $dbUser['id']),
            'permissions' => (new \App\Models\User())->permissionNames((int) $dbUser['id']),
        ]);
    }

    public function webChangePassword(Request $request): void
    {
        $data = $this->validator->changePassword($request->all());

        $userId = $request->getUserId();
        $sessionId = $request->getUser()['session_id'] ?? null;

        $this->authService->changePassword((int) $userId, $data['current_password'], $data['new_password'], $sessionId);

        if ($userId !== null) {
            $user = $request->getUser();
            $this->audit->log([
                'user_id' => $userId,
                'user_name' => $user['name'] ?? '',
                'user_role' => $user['role'] ?? '',
                'action' => 'PASSWORD_CHANGED',
                'module' => 'AUTH',
                'entity_type' => 'user',
                'entity_id' => $userId,
            ]);
        }

        Response::success(['message' => 'Password changed successfully']);
    }

    private function requireUser(Request $request): array
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthenticationException('UNAUTHENTICATED', 'Authentication required', 401);
        }
        return $user;
    }

    private function setCsrfCookie(): void
    {
        $secure = getenv('APP_ENV') === 'production';
        $token = $_COOKIE['csrf_token'] ?? bin2hex(random_bytes(16));
        setcookie('csrf_token', $token, [
            'expires' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['csrf_token'] = $token;
    }

    private function setSessionCookieLifetime(int $seconds): void
    {
        setcookie(session_name(), session_id(), [
            'expires' => time() + $seconds,
            'path' => '/',
            'secure' => getenv('APP_ENV') === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}