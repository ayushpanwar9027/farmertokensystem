<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        if ($request->method() === 'GET' || $request->method() === 'HEAD' || $request->method() === 'OPTIONS') {
            $this->primeCsrfCookie();
            $next($request);
            return;
        }

        $headerToken = $request->header('x-csrf-token', '');
        $cookieToken = $_COOKIE['csrf_token'] ?? '';

        if ($headerToken === '' || $cookieToken === '' || !hash_equals($cookieToken, $headerToken)) {
            Response::error('CSRF_TOKEN_MISMATCH', 'CSRF token is missing or invalid', 403);
            return;
        }

        $next($request);
    }

    private function primeCsrfCookie(): void
    {
        if (isset($_COOKIE['csrf_token']) && $_COOKIE['csrf_token'] !== '') {
            return;
        }

        $token = bin2hex(random_bytes(16));
        setcookie('csrf_token', $token, [
            'expires' => 0,
            'path' => '/',
            'secure' => getenv('APP_ENV') === 'production',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['csrf_token'] = $token;
    }
}
