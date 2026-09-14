<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AppException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Exceptions\MaintenanceException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'handleError'], E_ALL);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handle(\Throwable $e): void
    {
        self::logError($e);

        if ($e instanceof AppException) {
            self::handleAppException($e);
            return;
        }

        if (self::isProduction()) {
            Response::error('SERVER_ERROR', 'An unexpected error occurred', 500);
        } else {
            Response::error('SERVER_ERROR', $e->getMessage(), 500);
        }
    }

    private static function handleAppException(AppException $e): void
    {
        if ($e instanceof ValidationException) {
            Response::validationError($e->getErrors());
            return;
        }

        if ($e instanceof RateLimitException) {
            header('Retry-After: ' . $e->getRetryAfter());
            Response::error($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus(), $e->getDetails());
            return;
        }

        $details = $e->getDetails();
        if (!empty($details)) {
            Response::error($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus(), $details);
            return;
        }

        if (self::isProduction()) {
            Response::error($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus());
            return;
        }

        Response::error($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus());
    }

    public static function handleError(int $severity, string $message, string $file, int $line): void
    {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            self::logError($exception);

            if (!headers_sent()) {
                if (self::isProduction()) {
                    Response::error('SERVER_ERROR', 'An unexpected error occurred', 500);
                } else {
                    Response::error('SERVER_ERROR', $exception->getMessage(), 500);
                }
            }
        }
    }

    private static function logError(\Throwable $e): void
    {
        $requestId = Request::currentRequestId();

        $logEntry = [
            'timestamp' => gmdate('c'),
            'request_id' => $requestId,
            'level' => 'ERROR',
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];

        $logPath = dirname(__DIR__, 2) . '/storage/logs/application.log';
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logPath,
            json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function isProduction(): bool
    {
        return (getenv('APP_ENV') ?: 'development') === 'production';
    }
}