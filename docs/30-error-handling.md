# Error Handling & Logging

## Overview

Centralized error handling and logging. Separate logs for application, API, security, and notifications. Structured logging with request ID. Never log sensitive data.

## Log Directory Structure

```
storage/logs/
├── application.log     # General app errors/exceptions
├── api.log             # API requests/responses (sanitized)
├── security.log        # Auth, permission, rate-limit events
├── notification.log    # SMS/delivery logs
└── php_error.log       # PHP-level errors (from php.ini)
```

## Log Format (JSON Lines)

```json
{
  "timestamp": "2026-09-08T10:30:00Z",
  "request_id": "req_8f3a2b4c",
  "level": "ERROR",
  "type": "ValidationException",
  "endpoint": "POST /api/v1/bookings",
  "message": "...",
  "user_id": 101,
  "ip": "103.21.58.4",
  "file": "/app/Services/BookingService.php",
  "line": 42,
  "trace": "..."
}
```

## Logger

```php
// app/Helpers/Logger.php
class Logger {
    public static function log(string $file, string $level, string $message, array $context = []): void {
        $entry = array_merge([
            'timestamp' => gmdate('c'),
            'request_id' => Request::requestId(),
            'level' => $level,
        ], $context, ['message' => $message]);
        file_put_contents(
            storage_path("logs/$file"),
            json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public static function app($level, $msg, $ctx=[]) { self::log('application.log', $level, $msg, $ctx); }
    public static function api($msg, $ctx=[]) { self::log('api.log', 'INFO', $msg, $ctx); }
    public static function security($msg, $ctx=[]) { self::log('security.log', 'WARN', $msg, $ctx); }
    public static function notification($msg, $ctx=[]) { self::log('notification.log', 'INFO', $msg, $ctx); }
}
```

## Centralized Exception Handler

```php
// app/Core/ErrorHandler.php
class ErrorHandler {
    public static function handle(Throwable $e): void {
        // Log the error (details)
        self::log($e);

        // Record in error_logs table for tracking
        ErrorTracker::record($e, Request::context());

        // Map to safe response
        $resp = match(true) {
            $e instanceof ValidationException => self::validation($e),
            $e instanceof AuthenticationException => self::unauthorized(),
            $e instanceof AuthorizationException => self::forbidden(),
            $e instanceof NotFoundException => self::notFound(),
            $e instanceof ConflictException => self::conflict($e->getMessage()),
            $e instanceof RateLimitException => self::rateLimited(),
            $e instanceof MaintenanceException => self::maintenance(),
            $e instanceof DatabaseException => self::database(),
            default => self::server(),
        };
    }

    // Always: log details internally; send generic to user in production
}
```

## Never Log

- Passwords
- OTPs
- OneSignal / OTP gateway keys (API keys, app id)
- Encryption keys
- Session/refresh/remember tokens
- JWT bearer tokens (log presence, not value)
- Excessive personal data

Sanitize context before logging (strip sensitive keys).

## Error Tracker (Internal Error Tracking)

Track errors for monitoring, without third-party stack.

### error_logs table
```sql
CREATE TABLE error_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    request_id VARCHAR(64),
    endpoint VARCHAR(500),
    http_method VARCHAR(10),
    status_code INT,
    error_type VARCHAR(100),
    error_code VARCHAR(50),
    severity ENUM('INFO','WARNING','ERROR','CRITICAL') DEFAULT 'ERROR',
    message TEXT,
    file_path VARCHAR(500),
    line_number INT,
    stack_trace TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### ErrorTracker service
```php
class ErrorTracker {
    public static function record(Throwable $e, array $ctx): void { ... }
    public static function countsByEndpoint(): array { ... }   // for dashboard
    public static function recent(int $limit = 50): array { ... }
    public static function critical(): array { ... }
}
```

- Track: error count, endpoint, timestamp, severity, request ID, frequency, status
- Critical errors flagged (severity=CRITICAL) → identifiable quickly

## API Logging

In AuditMiddleware or dedicated route middleware, log API calls (sanitized):
```php
Logger::api('request', [
    'method' => $request->method(),
    'endpoint' => $request->path(),
    'status' => $responseStatus,
    'duration_ms' => $duration,
    'user_id' => $userId
]);
```

## Error Response Contract

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "details": { "field": ["msg"] }
  },
  "meta": { "request_id": "req_...", "timestamp": "..." }
}
```

Never include stack traces or internal fields in the client response.

---

**Next**: [31-logging-audit.md](31-logging-audit.md) for audit logging.