<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Services\AuthService;
use App\Services\RbacService;

class MaintenanceMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        $path = $request->path();

        if (in_array($path, ['/health', '/health/maintenance'], true)) {
            $next($request);
            return;
        }

        $row = Database::selectOne(
            "SELECT key_value FROM system_settings WHERE key_name = 'maintenance_mode'"
        );

        $maintenanceMode = $row !== null && in_array(strtolower((string) $row['key_value']), ['1', 'true', 'on', 'yes'], true);

        if ($maintenanceMode) {
            $user = $this->resolveBypassUser($request);

            if ($user !== null) {
                $request->setUser($user);
                $next($request);
                return;
            }

            $messageRow = Database::selectOne(
                "SELECT key_value FROM system_settings WHERE key_name = 'maintenance_message'"
            );
            $expectedRow = Database::selectOne(
                "SELECT key_value FROM system_settings WHERE key_name = 'maintenance_expected_available_at'"
            );

            $message = $messageRow !== null ? (string) $messageRow['key_value'] : '';
            if ($message === '') {
                $message = 'System is under maintenance';
            }

            Response::error(
                'MAINTENANCE_MODE',
                $message,
                503,
                ['expected_available_at' => $expectedRow !== null ? (string) $expectedRow['key_value'] : '']
            );
            return;
        }

        $next($request);
    }

    private function resolveBypassUser(Request $request): ?array
    {
        $token = $request->getBearerToken();
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $result = (new AuthService())->authenticateAccessToken($token);
        } catch (AuthenticationException $e) {
            return null;
        }

        $user = $result['user'];
        if (empty($user['is_super_admin'])) {
            return null;
        }

        $userId = (int) $user['id'];
        $roleName = $user['role'] ?? (new AuthService())->roleOf((int) $user['role_id']);

        $contextCandidate = [
            'id' => $userId,
            'role_id' => (int) ($user['role_id'] ?? 0),
            'role' => $roleName,
            'is_super_admin' => (int) ($user['is_super_admin'] ?? 0),
        ];

        return [
            'id' => $userId,
            'name' => $user['name'],
            'mobile' => $user['mobile'] ?? null,
            'email' => $user['email'] ?? null,
            'username' => $user['username'] ?? null,
            'role_id' => (int) ($user['role_id'] ?? 0),
            'role' => $roleName,
            'is_super_admin' => (int) ($user['is_super_admin'] ?? 0),
            'status' => $user['status'] ?? '',
            'verification_status' => $user['verification_status'] ?? 'APPROVED',
            'permissions' => (new RbacService())->effectivePermissions($userId, $contextCandidate),
            'session_id' => (int) ($result['session']['id'] ?? 0),
            'auth_method' => 'jwt',
        ];
    }
}