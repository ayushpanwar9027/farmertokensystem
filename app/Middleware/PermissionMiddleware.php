<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\RbacService;

class PermissionMiddleware implements MiddlewareInterface
{
    private array $requiredPermissions;

    public function __construct(?string $param = null)
    {
        $this->requiredPermissions = array_values(array_filter(array_map('trim', explode(',', (string) $param))));
    }

    public function handle(Request $request, callable $next): void
    {
        if (empty($this->requiredPermissions)) {
            $next($request);
            return;
        }

        $user = $request->getUser();
        $rbac = new RbacService();

        try {
            foreach ($this->requiredPermissions as $permission) {
                $rbac->assertCan($user, $permission, 'Missing required permission: ' . $permission);
            }
        } catch (AuthorizationException $e) {
            $this->logDenial($request, $user);
            Response::error('PERMISSION_DENIED', $e->getMessage(), 403);
            return;
        }

        $next($request);
    }

    private function logDenial(Request $request, ?array $user): void
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
                'type' => 'permission_denied',
                'required' => $this->requiredPermissions,
                'user_id' => $user['id'] ?? null,
                'role' => $user['role'] ?? null,
                'method' => $request->method(),
                'endpoint' => $request->uri(),
                'ip' => $request->ip(),
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}