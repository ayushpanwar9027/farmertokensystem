<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Farmer Procurement System'),
    'env' => env('APP_ENV', 'development'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'secret' => env('APP_SECRET', ''),
    'version' => env('APP_VERSION', '1.0.0'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),

    'session_lifetime' => (int) env('SESSION_LIFETIME', 1800),
    'remember_me_expiry' => (int) env('REMEMBER_ME_EXPIRY', 2592000),

    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'access_expiry' => (int) env('JWT_ACCESS_EXPIRY', 900),
        'refresh_expiry' => (int) env('JWT_REFRESH_EXPIRY', 604800),
    ],

    'otp' => [
        'length' => 6,
        'expiry' => (int) env('OTP_EXPIRY', 300),
        'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'max_resends' => (int) env('OTP_MAX_RESENDS', 3),
    ],

    'otp_gateway' => require __DIR__ . '/otp.php',

    'onesignal' => require __DIR__ . '/onesignal.php',

    'push' => require __DIR__ . '/push.php',

    'rate_limit_login' => (int) env('RATE_LIMIT_LOGIN', 5),
    'rate_limit_otp' => (int) env('RATE_LIMIT_OTP', 3),
    'rate_limit_booking' => (int) env('RATE_LIMIT_BOOKING', 10),
    'rate_limit_sms' => (int) env('RATE_LIMIT_SMS', 10),
    'rate_limit_api' => (int) env('RATE_LIMIT_API', 60),

    'cors' => [
        'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')))),
    ],

    'file_upload_max_size' => (int) env('FILE_UPLOAD_MAX_SIZE', 5242880),

    'notification_max_retries' => (int) env('NOTIFICATION_MAX_RETRIES', 3),
    'notification_retry_interval' => (int) env('NOTIFICATION_RETRY_INTERVAL', 300),
];
