<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\RbacService;

class RoleMiddleware implements MiddlewareInterface
{
    private ?int $minLevel;
    private array $allowedRoles;

    public function __construct(?string $param = null)
    {
        if ($param === null) {
            $this->minLevel = 30;
            $this->allowedRoles = [];
            return;
        }

        if (ctype_digit($param)) {
            $this->minLevel = (int) $param;
            $this->allowedRoles = [];
            return;
        }

        $this->minLevel = null;
        $this->allowedRoles = array_values(array_filter(array_map('trim', explode(',', $param))));
    }

    public function handle(Request $request, callable $next): void
    {
        $user = $request->getUser();
        if ($user === null) {
            $this->deny('Authentication required');
            return;
        }

        $rbac = new RbacService();

        try {
            $rbac->assertRoleLevel($user, $this->minLevel ?? 0, $this->allowedRoles);
        } catch (AuthorizationException $e) {
            $this->deny($e->getMessage());
            return;
        }

        $next($request);
    }

    private function deny(string $message): void
    {
        \App\Core\Response::error('ROLE_REQUIRED', $message, 403);
    }
}