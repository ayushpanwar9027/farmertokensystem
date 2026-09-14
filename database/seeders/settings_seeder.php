<?php

declare(strict_types=1);

function seedSettings(PDO $pdo): int
{
    $settings = [
        // GENERAL
        ['key' => 'system_name',                        'value' => 'Farmer Procurement System',  'type' => 'STRING', 'public' => 1, 'sensitive' => 0, 'group' => 'GENERAL',      'desc' => 'Display name of the system'],
        ['key' => 'default_language',                   'value' => 'en',                         'type' => 'STRING', 'public' => 1, 'sensitive' => 0, 'group' => 'GENERAL',      'desc' => 'Default language code'],
        ['key' => 'timezone',                           'value' => 'Asia/Kolkata',               'type' => 'STRING', 'public' => 1, 'sensitive' => 0, 'group' => 'GENERAL',      'desc' => 'System timezone'],
        ['key' => 'support_contact_phone',              'value' => '',                            'type' => 'STRING', 'public' => 1, 'sensitive' => 0, 'group' => 'GENERAL',      'desc' => 'Support phone number'],
        ['key' => 'support_contact_email',              'value' => '',                            'type' => 'STRING', 'public' => 1, 'sensitive' => 0, 'group' => 'GENERAL',      'desc' => 'Support email address'],
        // MAINTENANCE
        ['key' => 'maintenance_mode',                   'value' => '0',                          'type' => 'BOOL',   'public' => 1, 'sensitive' => 0, 'group' => 'MAINTENANCE',  'desc' => 'Enable maintenance mode'],
        ['key' => 'maintenance_message',                'value' => '',                            'type' => 'STRING', 'public' => 1, 'sensitive' => 0, 'group' => 'MAINTENANCE',  'desc' => 'Message shown during maintenance'],
        ['key' => 'maintenance_expected_available_at',  'value' => '',                            'type' => 'STRING', 'public' => 1, 'sensitive' => 0, 'group' => 'MAINTENANCE',  'desc' => 'Expected availability time'],
        // NOTIFICATION
        ['key' => 'push_enabled',                       'value' => '1',                          'type' => 'BOOL',   'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Enable OneSignal push notifications'],
        ['key' => 'sms_enabled',                        'value' => '1',                          'type' => 'BOOL',   'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Enable SMS notifications (OTP gateway)'],
        ['key' => 'notification_retry_count',           'value' => '3',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Max notification retry attempts'],
        ['key' => 'notification_retry_interval_seconds','value' => '300',                        'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Seconds between notification retries'],
        ['key' => 'queue_notification_threshold',      'value' => '3',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Notify farmer when N farmers ahead'],
        ['key' => 'sms_from_name',                      'value' => 'FPS',                        'type' => 'STRING', 'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'SMS sender name'],
        // BOOKING
        ['key' => 'booking_cancellation_window_minutes','value' => '120',                        'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Minutes before slot to allow cancellation'],
        ['key' => 'booking.horizon_days',               'value' => '7',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Max days ahead a farmer may book'],
        ['key' => 'booking.min_lead_hours',             'value' => '2',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Minimum hours before slot start to allow booking'],
        ['key' => 'booking.cancel_lead_minutes',        'value' => '120',                        'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Minutes before slot start to allow cancellation'],
        ['key' => 'booking.max_active',                 'value' => '1',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Maximum active (pending/confirmed) bookings a farmer may hold'],
        ['key' => 'auto_confirm_on_payment',            'value' => '0',                          'type' => 'BOOL',   'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'If true, confirm + token immediately; if false, wait for payment'],
        ['key' => 'max_crops_per_booking',              'value' => '10',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Maximum crops per booking'],
        ['key' => 'max_quantity_kg_per_booking',        'value' => '5000',                       'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Maximum total quantity (kg) per booking'],
        ['key' => 'slot.horizon_days',                  'value' => '7',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Days ahead to auto-generate slots'],
        ['key' => 'slot.default_duration_minutes',      'value' => '15',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Default slot duration in minutes'],
        ['key' => 'slot.default_capacity',              'value' => '10',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Default max tokens per slot'],
        ['key' => 'slot.min_capacity',                  'value' => '1',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Minimum allowed capacity per slot'],
        ['key' => 'slot.breaks',                        'value' => '[]',                         'type' => 'JSON',   'public' => 0, 'sensitive' => 0, 'group' => 'BOOKING',      'desc' => 'Break ranges excluded during slot generation'],
        // QUEUE
        ['key' => 'queue.avg_minutes_per_token',        'value' => '10',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'QUEUE',        'desc' => 'Average minutes per token for ETA estimates'],
        ['key' => 'queue.grace_no_show_minutes',        'value' => '5',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'QUEUE',        'desc' => 'Grace period (minutes) after a call before no-show can be marked'],
        ['key' => 'queue.notify_threshold',             'value' => '3',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'QUEUE',        'desc' => 'Notify farmer when farmers ahead drops to this threshold'],
        ['key' => 'queue.call_batch',                   'value' => '1',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'QUEUE',        'desc' => 'Number of farmers served per call-next action'],
        ['key' => 'queue.recall_limit',                 'value' => '1',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'QUEUE',        'desc' => 'Maximum times a skipped entry may be recalled to the end of the queue'],
        // PROCUREMENT
        ['key' => 'procurement.approval_required',      'value' => '1',                          'type' => 'BOOL',   'public' => 0, 'sensitive' => 0, 'group' => 'PROCUREMENT', 'desc' => 'Require manager/district approval after operator capture'],
        ['key' => 'procurement.max_photos',             'value' => '3',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'PROCUREMENT', 'desc' => 'Maximum QC photos per procurement'],
        ['key' => 'procurement.weight_precision',       'value' => '2',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'PROCUREMENT', 'desc' => 'Decimal precision for accepted/damaged weight capture'],
        // PAYMENTS
        ['key' => 'payment.allow_operator_release',     'value' => '0',                          'type' => 'BOOL',   'public' => 0, 'sensitive' => 0, 'group' => 'PAYMENTS',    'desc' => 'Allow centre operators to release payments (manager+ by default)'],
        // SECURITY & SESSION
        ['key' => 'session_timeout_minutes',            'value' => '30',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'SECURITY',     'desc' => 'Session timeout in minutes'],
        ['key' => 'remember_me_expiry_days',            'value' => '30',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'SECURITY',     'desc' => 'Remember-me token expiry in days'],
        ['key' => 'max_concurrent_sessions',            'value' => '10',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'SECURITY',     'desc' => 'Max concurrent sessions per user'],
        ['key' => 'max_login_attempts',                 'value' => '5',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'SECURITY',     'desc' => 'Max failed login attempts before lockout'],
        ['key' => 'lockout_minutes',                    'value' => '15',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'SECURITY',     'desc' => 'Lockout duration in minutes'],
        // FILE
        ['key' => 'file_upload_max_size_mb',            'value' => '5',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'FILE',         'desc' => 'Max file upload size in MB'],
        ['key' => 'allowed_file_types',                 'value' => '["jpg","png","pdf"]',        'type' => 'JSON',   'public' => 0, 'sensitive' => 0, 'group' => 'FILE',         'desc' => 'Allowed file extensions'],
        // RATE LIMIT
        ['key' => 'rate_limit_login_per_min',           'value' => '5',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'RATE_LIMIT',   'desc' => 'Max login attempts per minute'],
        ['key' => 'rate_limit_otp_per_5min',            'value' => '3',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'RATE_LIMIT',   'desc' => 'Max OTP requests per 5 minutes'],
        ['key' => 'rate_limit_booking_per_min',         'value' => '10',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'RATE_LIMIT',   'desc' => 'Max booking requests per minute'],
        ['key' => 'rate_limit_sms_per_min',             'value' => '10',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'RATE_LIMIT',   'desc' => 'Max SMS sends per minute'],
        ['key' => 'rate_limit_general_per_min',         'value' => '60',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'RATE_LIMIT',   'desc' => 'General rate limit per minute'],
        // NOTIFICATION (additional)
        ['key' => 'notification_language_default',      'value' => 'en',                         'type' => 'STRING', 'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Default language for notifications'],
        ['key' => 'otp_expiry_minutes',                 'value' => '5',                          'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'OTP validity in minutes'],
        ['key' => 'otp_resend_cooldown_seconds',        'value' => '60',                         'type' => 'INT',    'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Cooldown between OTP resends'],
        ['key' => 'two_factor_enabled_default',         'value' => '0',                          'type' => 'BOOL',   'public' => 0, 'sensitive' => 0, 'group' => 'NOTIFICATION', 'desc' => 'Default 2FA state for new users'],
    ];

    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (key_name, key_value, value_type, is_public, is_sensitive, group_name, description) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($settings as $s) {
        $stmt->execute([$s['key'], $s['value'], $s['type'], $s['public'], $s['sensitive'], $s['group'], $s['desc']]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
