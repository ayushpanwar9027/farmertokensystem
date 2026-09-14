<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private static bool $sent = false;

    public static function reset(): void
    {
        self::$sent = false;
    }

    public static function isSent(): bool
    {
        return self::$sent;
    }

    public static function json($data, int $status = 200, array $meta = []): void
    {
        self::$sent = true;
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $envelope = [
            'success' => $status >= 200 && $status < 400,
            'data' => $data,
            'meta' => array_merge(
                [
                    'timestamp' => gmdate('c'),
                    'request_id' => Request::currentRequestId(),
                    'locale' => Request::currentLocale(),
                ],
                $meta
            ),
        ];

        echo json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function success($data, array $meta = []): void
    {
        self::json($data, 200, $meta);
    }

    public static function created($data): void
    {
        self::json($data, 201);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): void
    {
        self::$sent = true;
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if (!empty($details)) {
            $error['details'] = $details;
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $envelope = [
            'success' => false,
            'error' => $error,
            'meta' => [
                'timestamp' => gmdate('c'),
                'request_id' => Request::currentRequestId(),
            ],
        ];

        echo json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error('NOT_FOUND', $message, 404);
    }

    public static function forbidden(string $message = 'Access denied'): void
    {
        self::error('FORBIDDEN', $message, 403);
    }

    public static function unauthorized(string $message = 'Authentication required'): void
    {
        self::error('UNAUTHENTICATED', $message, 401);
    }

    public static function validationError(array $errors): void
    {
        self::error('VALIDATION_ERROR', 'The given data was invalid', 400, $errors);
    }

    public static function conflict(string $message): void
    {
        self::error('CONFLICT', $message, 409);
    }

    public static function maintenance(string $message = 'System is under maintenance', string $estimated = ''): void
    {
        self::error('MAINTENANCE_MODE', $message, 503, $estimated !== '' ? ['expected_available_at' => $estimated] : []);
    }
}