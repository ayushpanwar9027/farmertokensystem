<?php

declare(strict_types=1);

return [
    'stacks' => [
        'api' => [
            \App\Middleware\MaintenanceMiddleware::class,
            \App\Middleware\CorsMiddleware::class,
            \App\Middleware\AuthMiddleware::class,
            \App\Middleware\LocaleMiddleware::class,
            \App\Middleware\RateLimitMiddleware::class,
        ],
        'web' => [
            \App\Middleware\MaintenanceMiddleware::class,
            \App\Middleware\CorsMiddleware::class,
            \App\Middleware\AuthMiddleware::class,
            \App\Middleware\LocaleMiddleware::class,
            \App\Middleware\CsrfMiddleware::class,
        ],
        'health' => [],
    ],
    'route_defaults' => [
        'admin_staff' => [
            \App\Middleware\RoleMiddleware::class . ':30',
            \App\Middleware\PermissionMiddleware::class . ':manage_staff',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'admin_catalog' => [
            \App\Middleware\RoleMiddleware::class . ':30',
            \App\Middleware\PermissionMiddleware::class . ':manage_staff',
        ],
        'admin_settings_view' => [
            \App\Middleware\PermissionMiddleware::class . ':settings.view',
        ],
        'admin_settings_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':settings.manage',
        ],
        'admin_secrets' => [
            \App\Middleware\PermissionMiddleware::class . ':settings.secret_manage',
        ],
        'admin_maintenance' => [
            \App\Middleware\PermissionMiddleware::class . ':maintenance.manage',
        ],
        'admin_translations_view' => [
            \App\Middleware\PermissionMiddleware::class . ':translations.view',
        ],
        'admin_translations_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':translations.manage',
        ],
        'files_upload' => [
            \App\Middleware\PermissionMiddleware::class . ':files.upload',
        ],
        'files_download' => [
            \App\Middleware\PermissionMiddleware::class . ':files.download',
        ],
        'files_delete' => [
            \App\Middleware\PermissionMiddleware::class . ':files.delete',
        ],
        'folders_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':files.folders',
        ],
        'files_admin' => [
            \App\Middleware\PermissionMiddleware::class . ':files.manage_all',
        ],
        'centres_view' => [
            \App\Middleware\PermissionMiddleware::class . ':centres.view',
        ],
        'centres_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':centres.manage',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'centres_status_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':centres.status.manage',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'staff_view' => [
            \App\Middleware\PermissionMiddleware::class . ':staff.view',
        ],
        'staff_manage' => [
            \App\Middleware\RoleMiddleware::class . ':70',
            \App\Middleware\PermissionMiddleware::class . ':staff.manage',
        ],
        'staff_centre_assign' => [
            \App\Middleware\RoleMiddleware::class . ':70',
            \App\Middleware\PermissionMiddleware::class . ':staff.manage',
            \App\Middleware\ScopeMiddleware::class . ':staff_centre',
        ],
        'farmers_view' => [
            \App\Middleware\PermissionMiddleware::class . ':view_farmers',
        ],
        'farmers_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':manage_farmers',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'districts_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':districts.manage',
        ],
        'slots_view' => [
            \App\Middleware\PermissionMiddleware::class . ':slots.view',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'slots_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':slots.manage',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'slots_cancel' => [
            \App\Middleware\PermissionMiddleware::class . ':slots.cancel',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'bookings_create' => [
            \App\Middleware\PermissionMiddleware::class . ':bookings.create',
        ],
        'bookings_view_own' => [
            \App\Middleware\PermissionMiddleware::class . ':bookings.view_own',
        ],
        'tokens_view_own' => [
            \App\Middleware\PermissionMiddleware::class . ':tokens.view_own',
        ],
        'bookings_view_any' => [
            \App\Middleware\PermissionMiddleware::class . ':bookings.view_any',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'bookings_cancel_any' => [
            \App\Middleware\PermissionMiddleware::class . ':bookings.cancel_any',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'queue_view' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.view',
        ],
        'queue_view_own' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.view_own',
        ],
        'queue_call_next' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.call_next',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'queue_skip' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.skip',
            \App\Middleware\ScopeMiddleware::class . ':queue_entry',
        ],
        'queue_no_show' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.no_show',
            \App\Middleware\ScopeMiddleware::class . ':queue_entry',
        ],
        'queue_recall' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.call_next',
            \App\Middleware\ScopeMiddleware::class . ':queue_entry',
        ],
        'operator_queue_view' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.view',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'queue_stats' => [
            \App\Middleware\PermissionMiddleware::class . ':queue.stats',
            \App\Middleware\ScopeMiddleware::class,
        ],
        'procurements_view_any' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.view_any',
        ],
        'procurements_show' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.view_any',
            \App\Middleware\ScopeMiddleware::class . ':procurement',
        ],
        'procurements_create' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.create',
            \App\Middleware\ScopeMiddleware::class . ':queue_entry',
        ],
        'procurements_update' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.update',
            \App\Middleware\ScopeMiddleware::class . ':procurement',
        ],
        'procurements_reject' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.reject',
            \App\Middleware\ScopeMiddleware::class . ':procurement',
        ],
        'procurements_view_own' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.view_own',
        ],
        'approvals_view' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.approve',
        ],
        'approvals_manage' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.approve',
            \App\Middleware\ScopeMiddleware::class . ':procurement',
        ],
        'approvals_show' => [
            \App\Middleware\PermissionMiddleware::class . ':procurements.approve',
            \App\Middleware\ScopeMiddleware::class . ':procurement',
        ],
        'payments_view' => [
            \App\Middleware\PermissionMiddleware::class . ':payments.view',
        ],
        'payments_show' => [
            \App\Middleware\PermissionMiddleware::class . ':payments.view',
            \App\Middleware\ScopeMiddleware::class . ':payment',
        ],
        'payments_release' => [
            \App\Middleware\PermissionMiddleware::class . ':payments.release',
            \App\Middleware\ScopeMiddleware::class . ':payment',
        ],
        'payments_cancel' => [
            \App\Middleware\PermissionMiddleware::class . ':payments.cancel',
            \App\Middleware\ScopeMiddleware::class . ':payment',
        ],
        'payments_reverse' => [
            \App\Middleware\PermissionMiddleware::class . ':payments.reverse',
            \App\Middleware\ScopeMiddleware::class . ':payment',
        ],
        'payments_view_own' => [
            \App\Middleware\PermissionMiddleware::class . ':view_own_payments',
        ],
        'rates_manage' => [
            \App\Middleware\RoleMiddleware::class . ':70',
            \App\Middleware\PermissionMiddleware::class . ':rates.manage',
        ],
        'rates_view' => [
            \App\Middleware\PermissionMiddleware::class . ':crops.view',
        ],
        'admin_audit' => [
            \App\Middleware\PermissionMiddleware::class . ':view_audit_logs',
        ],
    ],
];
