<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimiter;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        $limit = (int) env('RATE_LIMIT_API', 60);
        $window = 60;

        $key = $this->buildKey($request, 'api');
        $rateLimiter = new RateLimiter();

        $result = $rateLimiter->check($key, $limit, $window);

        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);

        if ($result['exceeded']) {
            $retryAfter = $result['retry_after'];
            header('Retry-After: ' . $retryAfter);
            Response::error('RATE_LIMIT_EXCEEDED', 'Too many requests. Please try again later.', 429, ['retry_after' => $retryAfter]);
            return;
        }

        $rateLimiter->increment($key, $window);

        $next($request);
    }

    private function buildKey(Request $request, string $prefix): string
    {
        $userId = $request->getUserId();
        if ($userId !== null) {
            return $prefix . ':user:' . $userId;
        }
        return $prefix . ':ip:' . $request->ip();
    }
}
