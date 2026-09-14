# Rate Limiting

## Overview

Rate limiting prevents brute force, SMS abuse, booking spam, and general API abuse. Shared-hosting compatible (file/DB based, no Redis).

## Targets

| Action | Limit | Window | Key |
|--------|-------|--------|-----|
| Login | 5 | per minute | mobile + IP |
| Login (global per IP) | 30 | per 10 min | IP |
| OTP send/verify | 3 | per 5 min | mobile |
| 2FA OTP (verify-2fa / 2fa enable/disable) | 3 | per 5 min | user (mobile) |
| SMS send | 10 | per minute | IP (global) |
| Booking create | 10 | per minute | user |
| Sensitive APIs (payment update) | 5 | per min | user |
| General API | 60 | per minute | IP/user |
| Queue polling | 12 | per min (i.e., every 5s) | user/device |

## Implementations

### 1. Database-backed limiter (preferred)

```sql
-- rate_limit_logs
CREATE TABLE rate_limit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    limiter_key VARCHAR(190) NOT NULL,   -- "login:192.168.1.5" etc.
    hits INT UNSIGNED DEFAULT 0,
    window_start DATETIME NOT NULL,
    window_end DATETIME NOT NULL,
    last_hit_at DATETIME,
    UNIQUE KEY (limiter_key, window_start)
);
```

```php
class RateLimiter {
    public static function exceeds(string $key, int $max, int $windowSeconds): bool {
        $windowStart = time() - $windowSeconds;
        // delete stale then count/insert
        // UPDATE hits=... / INSERT ... simple sliding window
    }

    public static function hit(string $key): void { ... }
}
```

- Simple, atomic-ish with GET_LOCK or upsert
- Add index on (limiter_key, window_end)
- Clean old rows via cron

### 2. File-based limiter (shared-hosting simplest)
Per-key file with hit count + window timestamp:
```php
function rateLimited(string $key, int $max, int $window) {
    $file = storage_path("/cache/rl_$key");
    // read JSON {count, window_start}
    // increment or reset if window expired
    // write back
}
```
Good for low-traffic; fine on shared hosting.

## Middleware

```php
// app/Middleware/RateLimitMiddleware.php
class RateLimitMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next) {
        $route = $request->getRouteAttribute('rate_limit'); // [key, max, window]
        if ($route) {
            [$key, $max, $window] = $route;
            $fullKey = "$key:" . $this->identityKey($request);
            if (RateLimiter::exceeds($fullKey, $max, $window)) {
                auditSecurity('RATE_LIMIT', $fullKey, $request);
                return Response::error('RATE_LIMITED', 'Too many requests', 429);
            }
            RateLimiter::hit($fullKey);
        }
        return $next($request);
    }

    private function identityKey(Request $r): string {
        return $r->getUserId() ? ("user:" . $r->getUserId()) : ("ip:" . $r->ip());
    }
}
```

### Route rate-limit config
```php
'POST /api/v1/auth/login'   => ['AuthController@login', ['rate_limit:login,5,60']],
'POST /api/v1/auth/verify'  => ['AuthController@verify', ['rate_limit:otp,3,300']],
'POST /api/v1/bookings'     => ['BookingController@store', ['auth', 'rate_limit:booking,10,60']],
```

## Response Headers

```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 8
X-RateLimit-Reset: 1726043400
Retry-After: 20
```

## LRU/Window Options

Use **sliding window** via per-key recent-hit timestamps, or fixed window. Fixed window is simplest and fine for SIH. Document choice.

## Login Lockout

Beyond generic rate limiting:
- After `max_login_attempts` (5) failures for a mobile → lockout `lockout_minutes` (15)
- Record in login_history as LOCKED
- Optionally require admin unlock after more failures
- Security log alert on spike

## SMS Abuse Prevention

- Dedicated SMS limiter (limit per mobile + per IP)
- Requires OTP/verified endpoint before sending
- Applies to notification SMS sends too (throttle)

## Cleanup Cron

- Delete old rate_limit_logs rows (> 1 day)
- Delete stale file-based limiters

## Audit & Logging

- Rate-limit violations → security.log
- Repeated violations → potential attack alert ([33-monitoring-alerts.md](33-monitoring-alerts.md))

## Concurrency Note

- Use simple upsert semantics to avoid race in counters
- Slight over/under counting acceptable; purpose is abuse prevention not exact counting

---

**Next**: [30-error-handling.md](30-error-handling.md) for error handling & logging.