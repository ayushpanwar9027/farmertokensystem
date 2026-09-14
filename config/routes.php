<?php

declare(strict_types=1);

$rbacMiddlewareConfig = require __DIR__ . '/middleware.php';
$rbacMiddlewares = $rbacMiddlewareConfig['route_defaults'];
$rbacAdminStaff = $rbacMiddlewares['admin_staff'];
$rbacAdminCatalog = $rbacMiddlewares['admin_catalog'];
$adminSettingsView = $rbacMiddlewares['admin_settings_view'];
$adminSettingsManage = $rbacMiddlewares['admin_settings_manage'];
$adminSecrets = $rbacMiddlewares['admin_secrets'];
$adminMaintenance = $rbacMiddlewares['admin_maintenance'];
$adminTranslationsView = $rbacMiddlewares['admin_translations_view'];
$adminTranslationsManage = $rbacMiddlewares['admin_translations_manage'];
$filesUpload = $rbacMiddlewares['files_upload'];
$filesDownload = $rbacMiddlewares['files_download'];
$filesDelete = $rbacMiddlewares['files_delete'];
$foldersManage = $rbacMiddlewares['folders_manage'];
$filesAdmin = $rbacMiddlewares['files_admin'];
$centresView = $rbacMiddlewares['centres_view'];
$centresManage = $rbacMiddlewares['centres_manage'];
$centresStatusManage = $rbacMiddlewares['centres_status_manage'];
$staffView = $rbacMiddlewares['staff_view'];
$staffManage = $rbacMiddlewares['staff_manage'];
$staffCentreAssign = $rbacMiddlewares['staff_centre_assign'];
$farmersView = $rbacMiddlewares['farmers_view'];
$farmersManage = $rbacMiddlewares['farmers_manage'];
$slotsView = $rbacMiddlewares['slots_view'];
$slotsManage = $rbacMiddlewares['slots_manage'];
$slotsCancel = $rbacMiddlewares['slots_cancel'];
$bookingsCreate = $rbacMiddlewares['bookings_create'];
$bookingsViewOwn = $rbacMiddlewares['bookings_view_own'];
$tokensViewOwn = $rbacMiddlewares['tokens_view_own'];
$bookingsViewAny = $rbacMiddlewares['bookings_view_any'];
$bookingsCancelAny = $rbacMiddlewares['bookings_cancel_any'];
$queueView = $rbacMiddlewares['queue_view'];
$queueViewOwn = $rbacMiddlewares['queue_view_own'];
$queueCallNext = $rbacMiddlewares['queue_call_next'];
$queueSkip = $rbacMiddlewares['queue_skip'];
$queueNoShow = $rbacMiddlewares['queue_no_show'];
$queueRecall = $rbacMiddlewares['queue_recall'];
$operatorQueueView = $rbacMiddlewares['operator_queue_view'];
$queueStats = $rbacMiddlewares['queue_stats'];
$procurementsViewAny = $rbacMiddlewares['procurements_view_any'];
$procurementsShow = $rbacMiddlewares['procurements_show'];
$procurementsCreate = $rbacMiddlewares['procurements_create'];
$procurementsUpdate = $rbacMiddlewares['procurements_update'];
$procurementsReject = $rbacMiddlewares['procurements_reject'];
$procurementsViewOwn = $rbacMiddlewares['procurements_view_own'];
$approvalsView = $rbacMiddlewares['approvals_view'];
$approvalsManage = $rbacMiddlewares['approvals_manage'];
$approvalsShow = $rbacMiddlewares['approvals_show'];
$paymentsView = $rbacMiddlewares['payments_view'];
$paymentsShow = $rbacMiddlewares['payments_show'];
$paymentsRelease = $rbacMiddlewares['payments_release'];
$paymentsCancel = $rbacMiddlewares['payments_cancel'];
$paymentsReverse = $rbacMiddlewares['payments_reverse'];
$paymentsViewOwn = $rbacMiddlewares['payments_view_own'];
$ratesManage = $rbacMiddlewares['rates_manage'];
$ratesView = $rbacMiddlewares['rates_view'];

$notificationsView = [
    \App\Middleware\PermissionMiddleware::class . ':notifications.view',
];
$notificationsTest = [
    \App\Middleware\RoleMiddleware::class . ':30',
    \App\Middleware\PermissionMiddleware::class . ':notifications.test',
];
$adminAudit = $rbacMiddlewares['admin_audit'];

$routes = [
    'GET /health' => [
        'handler' => 'HealthController@index',
        'stack' => 'health',
    ],
    'GET /health/maintenance' => [
        'handler' => 'HealthController@maintenance',
        'stack' => 'health',
    ],

    'POST /api/v1/test/echo' => [
        'handler' => 'TestController@echo',
        'stack' => 'api',
    ],
    'POST /api/v1/test/exception' => [
        'handler' => 'TestController@throwException',
        'stack' => 'api',
    ],
    'POST /api/v1/test/audit' => [
        'handler' => 'TestController@audit',
        'stack' => 'api',
    ],
    'GET /api/v1/test/manage-slots' => [
        'handler' => 'TestController@scopeCheck',
        'stack' => 'api',
        'middleware' => [
            \App\Middleware\PermissionMiddleware::class . ':manage_slots',
        ],
    ],
    'GET /api/v1/test/scoped-centre/{centre_id}' => [
        'handler' => 'TestController@scopeCheck',
        'stack' => 'api',
        'middleware' => [
            \App\Middleware\ScopeMiddleware::class . ':centre',
        ],
    ],
    'GET /api/v1/test/scoped-district/{district_id}' => [
        'handler' => 'TestController@scopeCheck',
        'stack' => 'api',
        'middleware' => [
            \App\Middleware\ScopeMiddleware::class . ':district',
        ],
    ],

    'POST /web/test/mutate' => [
        'handler' => 'TestController@webMutate',
        'stack' => 'web',
    ],

    'POST /api/v1/auth/register' => [
        'handler' => 'AuthController@register',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/verify-otp' => [
        'handler' => 'AuthController@verifyOtp',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/resend-otp' => [
        'handler' => 'AuthController@resendOtp',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/complete-registration' => [
        'handler' => 'AuthController@completeRegistration',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/login' => [
        'handler' => 'AuthController@login',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/verify-2fa' => [
        'handler' => 'AuthController@verify2fa',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/resend-2fa' => [
        'handler' => 'AuthController@resend2fa',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/2fa/enable' => [
        'handler' => 'AuthController@enable2fa',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/2fa/enable/confirm' => [
        'handler' => 'AuthController@confirmEnable2fa',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/2fa/disable' => [
        'handler' => 'AuthController@disable2fa',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/2fa/challenge' => [
        'handler' => 'AuthController@challenge2fa',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/2fa/confirm' => [
        'handler' => 'AuthController@confirmStepUp',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/devices' => [
        'handler' => 'SessionController@registerDevice',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/logout' => [
        'handler' => 'AuthController@logout',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/refresh' => [
        'handler' => 'AuthController@refresh',
        'stack' => 'api',
    ],
    'GET /api/v1/auth/me' => [
        'handler' => 'AuthController@me',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/forgot-password' => [
        'handler' => 'AuthController@forgotPassword',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/reset-password' => [
        'handler' => 'AuthController@resetPassword',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/sessions/refresh-current' => [
        'handler' => 'SessionController@refreshCurrent',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/sessions/revoke-all' => [
        'handler' => 'SessionController@revokeAll',
        'stack' => 'api',
    ],
    'GET /api/v1/auth/sessions' => [
        'handler' => 'SessionController@list',
        'stack' => 'api',
    ],
    'POST /api/v1/auth/sessions/{id}/revoke' => [
        'handler' => 'SessionController@revoke',
        'stack' => 'api',
    ],
    'GET /api/v1/auth/login-history' => [
        'handler' => 'SessionController@loginHistory',
        'stack' => 'api',
    ],

    'GET /api/v1/admin/staff' => [
        'handler' => 'Admin\StaffController@index',
        'stack' => 'api',
        'middleware' => $staffView,
    ],
    'POST /api/v1/admin/staff' => [
        'handler' => 'Admin\StaffController@store',
        'stack' => 'api',
        'middleware' => $staffManage,
    ],
    'GET /api/v1/admin/staff/{id}' => [
        'handler' => 'Admin\StaffController@show',
        'stack' => 'api',
        'middleware' => $staffView,
    ],
    'PUT /api/v1/admin/staff/{id}' => [
        'handler' => 'Admin\StaffController@update',
        'stack' => 'api',
        'middleware' => $staffManage,
    ],
    'PUT /api/v1/admin/staff/{id}/status' => [
        'handler' => 'Admin\StaffController@status',
        'stack' => 'api',
        'middleware' => $staffManage,
    ],
    'PUT /api/v1/admin/staff/{id}/centre' => [
        'handler' => 'Admin\StaffController@centre',
        'stack' => 'api',
        'middleware' => $staffCentreAssign,
    ],
    'PUT /api/v1/admin/staff/{id}/permissions' => [
        'handler' => 'Admin\RolePermissionController@permissions',
        'stack' => 'api',
        'middleware' => $rbacAdminStaff,
    ],
    'PUT /api/v1/admin/staff/{id}/role' => [
        'handler' => 'Admin\RolePermissionController@assignRole',
        'stack' => 'api',
        'middleware' => $rbacAdminStaff,
    ],
    'GET /api/v1/admin/farmers' => [
        'handler' => 'Admin\FarmerController@index',
        'stack' => 'api',
        'middleware' => $farmersView,
    ],
    'POST /api/v1/admin/farmers' => [
        'handler' => 'Admin\FarmerController@store',
        'stack' => 'api',
        'middleware' => $farmersManage,
    ],
    'GET /api/v1/admin/farmers/{id}' => [
        'handler' => 'Admin\FarmerController@show',
        'stack' => 'api',
        'middleware' => $farmersView,
    ],
    'PUT /api/v1/admin/farmers/{id}/password' => [
        'handler' => 'Admin\FarmerController@resetPassword',
        'stack' => 'api',
        'middleware' => $farmersManage,
    ],
    'PUT /api/v1/admin/farmers/{id}/status' => [
        'handler' => 'Admin\FarmerController@status',
        'stack' => 'api',
        'middleware' => $farmersManage,
    ],
    'GET /api/v1/admin/roles' => [
        'handler' => 'Admin\RolePermissionController@roles',
        'stack' => 'api',
        'middleware' => $rbacAdminCatalog,
    ],
    'GET /api/v1/admin/permissions' => [
        'handler' => 'Admin\RolePermissionController@permissionCatalog',
        'stack' => 'api',
        'middleware' => $rbacAdminCatalog,
    ],

    'GET /api/v1/districts' => [
        'handler' => 'DistrictController@index',
        'stack' => 'api',
    ],
    'GET /api/v1/centres' => [
        'handler' => 'Admin\CentreController@list',
        'stack' => 'api',
    ],
    'GET /api/v1/admin/centres/{id}' => [
        'handler' => 'Admin\CentreController@show',
        'stack' => 'api',
        'middleware' => $centresView,
    ],
    'POST /api/v1/admin/centres' => [
        'handler' => 'Admin\CentreController@store',
        'stack' => 'api',
        'middleware' => $centresManage,
    ],
    'PUT /api/v1/admin/centres/{id}' => [
        'handler' => 'Admin\CentreController@update',
        'stack' => 'api',
        'middleware' => $centresManage,
    ],
    'POST /api/v1/admin/centres/{id}/status' => [
        'handler' => 'Admin\CentreController@status',
        'stack' => 'api',
        'middleware' => $centresStatusManage,
    ],

    'GET /api/v1/slots' => [
        'handler' => 'Admin\SlotController@bookable',
        'stack' => 'api',
    ],
    'GET /api/v1/admin/slots' => [
        'handler' => 'Admin\SlotController@index',
        'stack' => 'api',
        'middleware' => $slotsView,
    ],
    'POST /api/v1/admin/slots/generate' => [
        'handler' => 'Admin\SlotController@generate',
        'stack' => 'api',
        'middleware' => $slotsManage,
    ],
    'POST /api/v1/admin/slots' => [
        'handler' => 'Admin\SlotController@store',
        'stack' => 'api',
        'middleware' => $slotsManage,
    ],
    'GET /api/v1/admin/slots/{id}' => [
        'handler' => 'Admin\SlotController@show',
        'stack' => 'api',
        'middleware' => $slotsView,
    ],
    'PUT /api/v1/admin/slots/{id}' => [
        'handler' => 'Admin\SlotController@update',
        'stack' => 'api',
        'middleware' => $slotsManage,
    ],
    'DELETE /api/v1/admin/slots/{id}' => [
        'handler' => 'Admin\SlotController@destroy',
        'stack' => 'api',
        'middleware' => $slotsManage,
    ],

    'GET /api/v1/crops' => [
        'handler' => 'CropController@index',
        'stack' => 'api',
    ],
    'GET /api/v1/crops/{cropId}/rates' => [
        'handler' => 'Admin\PaymentAdminController@getCropRates',
        'stack' => 'api',
        'middleware' => $ratesView,
    ],
    'GET /api/v1/bookings' => [
        'handler' => 'BookingController@index',
        'stack' => 'api',
        'middleware' => $bookingsViewOwn,
    ],
    'POST /api/v1/bookings' => [
        'handler' => 'BookingController@store',
        'stack' => 'api',
        'middleware' => $bookingsCreate,
    ],
    'GET /api/v1/bookings/{id}' => [
        'handler' => 'BookingController@show',
        'stack' => 'api',
        'middleware' => $bookingsViewOwn,
    ],
    'GET /api/v1/bookings/{id}/crops' => [
        'handler' => 'BookingController@crops',
        'stack' => 'api',
        'middleware' => $bookingsViewOwn,
    ],
    'POST /api/v1/bookings/{id}/cancel' => [
        'handler' => 'BookingController@cancel',
        'stack' => 'api',
        'middleware' => $bookingsViewOwn,
    ],
    'GET /api/v1/my/token' => [
        'handler' => 'BookingController@myToken',
        'stack' => 'api',
        'middleware' => $tokensViewOwn,
    ],
    'GET /api/v1/admin/bookings' => [
        'handler' => 'Admin\BookingAdminController@index',
        'stack' => 'api',
        'middleware' => $bookingsViewAny,
    ],
    'GET /api/v1/admin/bookings/{id}' => [
        'handler' => 'Admin\BookingAdminController@show',
        'stack' => 'api',
        'middleware' => $bookingsViewAny,
    ],
    'POST /api/v1/admin/bookings/{id}/cancel' => [
        'handler' => 'Admin\BookingAdminController@cancel',
        'stack' => 'api',
        'middleware' => $bookingsCancelAny,
    ],
    'GET /api/v1/queue/my' => [
        'handler' => 'QueueController@my',
        'stack' => 'api',
        'middleware' => $queueViewOwn,
    ],
    'GET /api/v1/queue/live' => [
        'handler' => 'QueueController@live',
        'stack' => 'api',
        'middleware' => $queueView,
    ],
    'GET /api/v1/queue/{bookingToken}/status' => [
        'handler' => 'QueueController@status',
        'stack' => 'api',
        'middleware' => $queueViewOwn,
    ],
    'GET /api/v1/operator/queue' => [
        'handler' => 'Operator\QueueOperatorController@index',
        'stack' => 'api',
        'middleware' => $operatorQueueView,
    ],
    'GET /api/v1/operator/queue/stats' => [
        'handler' => 'Operator\QueueOperatorController@stats',
        'stack' => 'api',
        'middleware' => $queueStats,
    ],
    'POST /api/v1/operator/queue/call-next' => [
        'handler' => 'Operator\QueueOperatorController@callNext',
        'stack' => 'api',
        'middleware' => $queueCallNext,
    ],
    'POST /api/v1/operator/queue/{entryId}/skip' => [
        'handler' => 'Operator\QueueOperatorController@skip',
        'stack' => 'api',
        'middleware' => $queueSkip,
    ],
    'POST /api/v1/operator/queue/{entryId}/no-show' => [
        'handler' => 'Operator\QueueOperatorController@noShow',
        'stack' => 'api',
        'middleware' => $queueNoShow,
    ],
    'POST /api/v1/operator/queue/{entryId}/recall' => [
        'handler' => 'Operator\QueueOperatorController@recall',
        'stack' => 'api',
        'middleware' => $queueRecall,
    ],

    'POST /api/v1/operator/queue/{entryId}/start' => [
        'handler' => 'Operator\ProcurementController@start',
        'stack' => 'api',
        'middleware' => $procurementsCreate,
    ],
    'PUT /api/v1/operator/procurements/{id}' => [
        'handler' => 'Operator\ProcurementController@capture',
        'stack' => 'api',
        'middleware' => $procurementsUpdate,
    ],
    'POST /api/v1/operator/procurements/{id}/submit' => [
        'handler' => 'Operator\ProcurementController@submit',
        'stack' => 'api',
        'middleware' => $procurementsUpdate,
    ],
    'POST /api/v1/operator/procurements/{id}/reject' => [
        'handler' => 'Operator\ProcurementController@reject',
        'stack' => 'api',
        'middleware' => $procurementsReject,
    ],
    'GET /api/v1/operator/procurements' => [
        'handler' => 'Operator\ProcurementController@index',
        'stack' => 'api',
        'middleware' => $procurementsViewAny,
    ],
    'GET /api/v1/operator/procurements/{id}' => [
        'handler' => 'Operator\ProcurementController@show',
        'stack' => 'api',
        'middleware' => $procurementsShow,
    ],

    'GET /api/v1/admin/approvals' => [
        'handler' => 'Admin\ApprovalController@index',
        'stack' => 'api',
        'middleware' => $approvalsView,
    ],
    'GET /api/v1/admin/approvals/{id}' => [
        'handler' => 'Admin\ApprovalController@show',
        'stack' => 'api',
        'middleware' => $approvalsShow,
    ],
    'POST /api/v1/admin/approvals/{id}/approve' => [
        'handler' => 'Admin\ApprovalController@approve',
        'stack' => 'api',
        'middleware' => $approvalsManage,
    ],
    'POST /api/v1/admin/approvals/{id}/reject' => [
        'handler' => 'Admin\ApprovalController@reject',
        'stack' => 'api',
        'middleware' => $approvalsManage,
    ],
    'GET /api/v1/admin/procurements/{id}' => [
        'handler' => 'Admin\ApprovalController@show',
        'stack' => 'api',
        'middleware' => $approvalsShow,
    ],

    'GET /api/v1/my/procurements' => [
        'handler' => 'MyProcurementController@index',
        'stack' => 'api',
        'middleware' => $procurementsViewOwn,
    ],
    'GET /api/v1/my/procurements/{id}' => [
        'handler' => 'MyProcurementController@show',
        'stack' => 'api',
        'middleware' => $procurementsViewOwn,
    ],
    'GET /api/v1/my/payments' => [
        'handler' => 'MyPaymentController@index',
        'stack' => 'api',
        'middleware' => $paymentsViewOwn,
    ],
    'GET /api/v1/my/payments/{id}' => [
        'handler' => 'MyPaymentController@show',
        'stack' => 'api',
        'middleware' => $paymentsViewOwn,
    ],

    'GET /api/v1/notifications' => [
        'handler' => 'NotificationsController@index',
        'stack' => 'api',
    ],
    'GET /api/v1/notifications/{id}' => [
        'handler' => 'NotificationsController@show',
        'stack' => 'api',
    ],
    'PATCH /api/v1/notifications/{id}/read' => [
        'handler' => 'NotificationsController@markRead',
        'stack' => 'api',
    ],
    'PATCH /api/v1/notifications/read-all' => [
        'handler' => 'NotificationsController@markAllRead',
        'stack' => 'api',
    ],
    'GET /api/v1/admin/notifications' => [
        'handler' => 'NotificationsController@adminIndex',
        'stack' => 'api',
        'middleware' => $notificationsView,
    ],
    'GET /api/v1/admin/notifications/summary' => [
        'handler' => 'NotificationsController@adminSummary',
        'stack' => 'api',
        'middleware' => $notificationsView,
    ],
    'POST /api/v1/admin/notifications/test-push' => [
        'handler' => 'NotificationsController@testPush',
        'stack' => 'api',
        'middleware' => $notificationsTest,
    ],

    'GET /api/v1/admin/audit' => [
        'handler' => 'Admin\AuditController@index',
        'stack' => 'api',
        'middleware' => $adminAudit,
    ],
    'GET /api/v1/admin/audit/{id}' => [
        'handler' => 'Admin\AuditController@show',
        'stack' => 'api',
        'middleware' => $adminAudit,
    ],

    'GET /api/v1/operator/payments' => [
        'handler' => 'Operator\PaymentController@index',
        'stack' => 'api',
        'middleware' => $paymentsView,
    ],
    'GET /api/v1/operator/payments/{id}' => [
        'handler' => 'Operator\PaymentController@show',
        'stack' => 'api',
        'middleware' => $paymentsShow,
    ],
    'PUT /api/v1/operator/payments/{id}/release' => [
        'handler' => 'Operator\PaymentController@release',
        'stack' => 'api',
        'middleware' => $paymentsRelease,
    ],

    'GET /api/v1/admin/payments' => [
        'handler' => 'Admin\PaymentAdminController@index',
        'stack' => 'api',
        'middleware' => $paymentsView,
    ],
    'GET /api/v1/admin/payments/{id}' => [
        'handler' => 'Admin\PaymentAdminController@show',
        'stack' => 'api',
        'middleware' => $paymentsShow,
    ],
    'POST /api/v1/admin/payments/{id}/cancel' => [
        'handler' => 'Admin\PaymentAdminController@cancel',
        'stack' => 'api',
        'middleware' => $paymentsCancel,
    ],
    'POST /api/v1/admin/payments/{id}/reverse' => [
        'handler' => 'Admin\PaymentAdminController@reverse',
        'stack' => 'api',
        'middleware' => $paymentsReverse,
    ],

    'GET /api/v1/admin/crop-rates' => [
        'handler' => 'Admin\PaymentAdminController@listRates',
        'stack' => 'api',
        'middleware' => $ratesManage,
    ],
    'POST /api/v1/admin/crop-rates' => [
        'handler' => 'Admin\PaymentAdminController@storeRate',
        'stack' => 'api',
        'middleware' => $ratesManage,
    ],
    'PUT /api/v1/admin/crop-rates/{id}' => [
        'handler' => 'Admin\PaymentAdminController@updateRate',
        'stack' => 'api',
        'middleware' => $ratesManage,
    ],

    'GET /api/v1/admin/settings' => [
        'handler' => 'Admin\SettingController@index',
        'stack' => 'api',
        'middleware' => $adminSettingsView,
    ],
    'PUT /api/v1/admin/settings' => [
        'handler' => 'Admin\SettingController@update',
        'stack' => 'api',
        'middleware' => $adminSettingsManage,
    ],
    'GET /api/v1/admin/secrets' => [
        'handler' => 'Admin\SecretController@index',
        'stack' => 'api',
        'middleware' => $adminSecrets,
    ],
    'PUT /api/v1/admin/secrets' => [
        'handler' => 'Admin\SecretController@update',
        'stack' => 'api',
        'middleware' => $adminSecrets,
    ],
    'PUT /api/v1/admin/maintenance' => [
        'handler' => 'Admin\MaintenanceController@update',
        'stack' => 'api',
        'middleware' => $adminMaintenance,
    ],

    'GET /api/v1/translations/{locale}' => [
        'handler' => 'TranslationsController@show',
        'stack' => 'api',
    ],
    'GET /api/v1/languages' => [
        'handler' => 'TranslationsController@languages',
        'stack' => 'api',
    ],
    'GET /api/v1/admin/translations' => [
        'handler' => 'TranslationsController@index',
        'stack' => 'api',
        'middleware' => $adminTranslationsView,
    ],
    'GET /api/v1/admin/translations/export' => [
        'handler' => 'TranslationsController@export',
        'stack' => 'api',
        'middleware' => $adminTranslationsView,
    ],
    'POST /api/v1/admin/translations/import' => [
        'handler' => 'TranslationsController@import',
        'stack' => 'api',
        'middleware' => $adminTranslationsManage,
    ],
    'PUT /api/v1/admin/languages/{code}/translations' => [
        'handler' => 'TranslationsController@bulkUpdate',
        'stack' => 'api',
        'middleware' => $adminTranslationsManage,
    ],
    'DELETE /api/v1/admin/languages/{code}/translations' => [
        'handler' => 'TranslationsController@deleteKey',
        'stack' => 'api',
        'middleware' => $adminTranslationsManage,
    ],

    'POST /api/v1/files/upload' => [
        'handler' => 'FileController@upload',
        'stack' => 'api',
        'middleware' => $filesUpload,
    ],
    'GET /api/v1/files' => [
        'handler' => 'FileController@index',
        'stack' => 'api',
        'middleware' => $filesDownload,
    ],
    'GET /api/v1/files/folders' => [
        'handler' => 'FileController@folders',
        'stack' => 'api',
        'middleware' => $foldersManage,
    ],
    'POST /api/v1/files/folders' => [
        'handler' => 'FileController@storeFolder',
        'stack' => 'api',
        'middleware' => $foldersManage,
    ],
    'GET /api/v1/files/{id}' => [
        'handler' => 'FileController@serve',
        'stack' => 'api',
        'middleware' => $filesDownload,
    ],
    'DELETE /api/v1/files/{id}' => [
        'handler' => 'FileController@destroy',
        'stack' => 'api',
        'middleware' => $filesDelete,
    ],

    'GET /api/v1/admin/files' => [
        'handler' => 'Admin\FileManagerController@index',
        'stack' => 'api',
        'middleware' => $filesAdmin,
    ],
    'DELETE /api/v1/admin/files/{id}' => [
        'handler' => 'Admin\FileManagerController@destroy',
        'stack' => 'api',
        'middleware' => $filesAdmin,
    ],

    'GET /web/csrf' => [
        'handler' => 'AuthController@webCsrf',
        'stack' => 'web',
    ],
    'POST /web/login' => [
        'handler' => 'AuthController@webLogin',
        'stack' => 'web',
    ],
    'POST /web/verify-2fa' => [
        'handler' => 'AuthController@webVerify2fa',
        'stack' => 'web',
    ],
    'POST /web/logout' => [
        'handler' => 'AuthController@webLogout',
        'stack' => 'web',
    ],
    'GET /web/me' => [
        'handler' => 'AuthController@webMe',
        'stack' => 'web',
    ],
    'POST /web/password/change' => [
        'handler' => 'AuthController@webChangePassword',
        'stack' => 'web',
    ],
];

// Dev-only routes. Never registered in production (no debug/test endpoints).
if (env('APP_ENV', 'development') === 'production') {
    foreach ($routes as $routeKey => $routeConfig) {
        if (strpos($routeKey, '/test/') !== false) {
            unset($routes[$routeKey]);
        }
    }
}

return $routes;