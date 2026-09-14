<?php

declare(strict_types=1);

return [
    'disk' => [
        'root' => storage_path('app/private/files'),
        'visibility' => 'private',
    ],

    'allowed_extensions' => array_values(array_filter(array_map(
        'strtolower',
        array_map('trim', explode(',', (string) env('ALLOWED_MIME_TYPES', 'jpg,png,webp,pdf,csv,xlsx')))
    ))),

    'max_size' => (int) env('FILE_UPLOAD_MAX_SIZE', 5242880),

    'mime_map' => [
        'jpg'  => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
        'png'  => ['image/png', 'image/x-png'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'csv'  => ['text/csv', 'application/csv', 'text/x-csv', 'text/plain'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ],

    'inline_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
];