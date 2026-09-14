<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        $allowedOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $allowAll = in_array('*', $allowedOrigins, true);
        $isAllowed = $allowAll || in_array($origin, $allowedOrigins, true);

        if ($isAllowed && $origin !== '') {
            header('Access-Control-Allow-Origin: ' . ($allowAll ? '*' : $origin));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Request-ID, Accept-Language, X-Device-ID, X-Platform, X-App-Version');
        header('Access-Control-Expose-Headers: X-Request-ID, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        $next($request);
    }
}
