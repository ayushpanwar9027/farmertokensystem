<?php

declare(strict_types=1);

function seedPermissions(PDO $pdo): int
{
    $permissions = [
        // FARMERS
        ['name' => 'view_farmers',             'display_name' => 'View Farmers',             'module' => 'FARMERS',       'description' => 'View farmer profiles and list'],
        ['name' => 'manage_farmers',           'display_name' => 'Manage Farmers',           'module' => 'FARMERS',       'description' => 'Create, update, verify, and manage farmers'],
        // CENTRES
        ['name' => 'view_centres',             'display_name' => 'View Centres',             'module' => 'CENTRES',       'description' => 'View procurement centres'],
        ['name' => 'manage_centres',           'display_name' => 'Manage Centres',           'module' => 'CENTRES',       'description' => 'Create, update, and manage procurement centres'],
        // SLOTS
        ['name' => 'view_slots',               'display_name' => 'View Slots',               'module' => 'SLOTS',         'description' => 'View time slots'],
        ['name' => 'manage_slots',             'display_name' => 'Manage Slots',             'module' => 'SLOTS',         'description' => 'Create, update, and manage time slots'],
        // QUEUE
        ['name' => 'view_queue',               'display_name' => 'View Queue',               'module' => 'QUEUE',         'description' => 'View queue entries and live queue'],
        ['name' => 'manage_queue',             'display_name' => 'Manage Queue',             'module' => 'QUEUE',         'description' => 'Call next, skip, manage queue entries'],
        // PROCUREMENT
        ['name' => 'view_procurement',         'display_name' => 'View Procurement',         'module' => 'PROCUREMENT',   'description' => 'View procurement records'],
        ['name' => 'manage_procurement',       'display_name' => 'Manage Procurement',       'module' => 'PROCUREMENT',   'description' => 'Create, verify, and manage procurement records'],
        // PAYMENTS
        ['name' => 'view_payments',            'display_name' => 'View Payments',            'module' => 'PAYMENTS',      'description' => 'View payment records'],
        ['name' => 'manage_payments',          'display_name' => 'Manage Payments',          'module' => 'PAYMENTS',      'description' => 'Process and manage payments'],
        // REPORTS
        ['name' => 'view_reports',             'display_name' => 'View Reports',             'module' => 'REPORTS',       'description' => 'View reports and analytics'],
        // STAFF
        ['name' => 'manage_staff',             'display_name' => 'Manage Staff',             'module' => 'STAFF',         'description' => 'Create, update, and manage staff accounts'],
        // CORRECTIONS
        ['name' => 'approve_corrections',      'display_name' => 'Approve Corrections',      'module' => 'CORRECTIONS',   'description' => 'Approve or reject correction requests'],
        // AUDIT
        ['name' => 'view_audit_logs',          'display_name' => 'View Audit Logs',          'module' => 'AUDIT',         'description' => 'View audit log entries'],
        // LANGUAGES
        ['name' => 'manage_languages',         'display_name' => 'Manage Languages',         'module' => 'LANGUAGES',     'description' => 'Add, edit, enable/disable languages'],
        // FILES
        ['name' => 'manage_files',             'display_name' => 'Manage Files',             'module' => 'FILES',         'description' => 'Upload, manage, and delete files'],
        // SETTINGS
        ['name' => 'manage_system_settings',   'display_name' => 'Manage System Settings',   'module' => 'SETTINGS',      'description' => 'View and modify system configuration'],
        ['name' => 'settings.view',            'display_name' => 'View System Settings',     'module' => 'SETTINGS',      'description' => 'View system settings and configuration'],
        ['name' => 'settings.manage',          'display_name' => 'Manage System Settings',   'module' => 'SETTINGS',      'description' => 'Modify system settings with type validation'],
        // SESSIONS
        ['name' => 'manage_sessions',          'display_name' => 'Manage Sessions',          'module' => 'SESSIONS',      'description' => 'View and revoke user sessions'],
        // SECRETS
        ['name' => 'manage_secrets',           'display_name' => 'Manage Secrets',           'module' => 'SECRETS',       'description' => 'View and manage encrypted secrets'],
        ['name' => 'settings.secret_manage',   'display_name' => 'Manage Secrets',           'module' => 'SECRETS',       'description' => 'View metadata and rotate encrypted secrets'],
        // MAINTENANCE
        ['name' => 'manage_maintenance',       'display_name' => 'Manage Maintenance',       'module' => 'MAINTENANCE',   'description' => 'Toggle and configure maintenance mode'],
        ['name' => 'maintenance.manage',       'display_name' => 'Manage Maintenance',       'module' => 'MAINTENANCE',   'description' => 'Toggle and configure maintenance mode'],
        // FARMER SELF-SERVICE
        ['name' => 'manage_own_profile',       'display_name' => 'Manage Own Profile',       'module' => 'PROFILE',       'description' => 'View and update own profile'],
        ['name' => 'book_slot',                'display_name' => 'Book Slot',                'module' => 'BOOKINGS',      'description' => 'Book a time slot for procurement'],
        ['name' => 'manage_own_bookings',      'display_name' => 'Manage Own Bookings',      'module' => 'BOOKINGS',      'description' => 'View and cancel own bookings'],
        ['name' => 'view_own_queue',           'display_name' => 'View Own Queue',           'module' => 'QUEUE',         'description' => 'View own position in the queue'],
        ['name' => 'view_own_procurement',     'display_name' => 'View Own Procurement',     'module' => 'PROCUREMENT',   'description' => 'View own procurement records'],
        ['name' => 'view_own_payments',        'display_name' => 'View Own Payments',        'module' => 'PAYMENTS',      'description' => 'View own payment records'],
        // LANGUAGE SYSTEM
        ['name' => 'translations.view',        'display_name' => 'View Translations',     'module' => 'LANGUAGE',  'description' => 'View translation packs and the language list'],
        ['name' => 'translations.manage',      'display_name' => 'Manage Translations',   'module' => 'LANGUAGE',  'description' => 'Bulk update, import and export translations'],
        // FILE MANAGER
        ['name' => 'files.upload',             'display_name' => 'Upload Files',          'module' => 'FILES',     'description' => 'Upload files to the file manager'],
        ['name' => 'files.download',           'display_name' => 'Download Files',        'module' => 'FILES',     'description' => 'View, list and download files'],
        ['name' => 'files.delete',             'display_name' => 'Delete Files',          'module' => 'FILES',     'description' => 'Delete accessible files'],
        ['name' => 'files.folders',            'display_name' => 'Manage Folders',        'module' => 'FILES',     'description' => 'Create and manage file folders'],
        ['name' => 'files.manage_all',         'display_name' => 'Admin File Manager',    'module' => 'FILES',     'description' => 'List all files and force-delete files'],
        // PHASE 08 — PROCUREMENT CENTRES + STAFF + DISTRICTS
        ['name' => 'centres.view',             'display_name' => 'View Centres',          'module' => 'CENTRES',   'description' => 'View procurement centres (all roles incl. farmer)', 'is_system' => 1],
        ['name' => 'centres.manage',           'display_name' => 'Manage Centres',        'module' => 'CENTRES',   'description' => 'Create and update procurement centres (manager+)', 'is_system' => 1],
        ['name' => 'centres.status.manage',    'display_name' => 'Manage Centre Status',  'module' => 'CENTRES',   'description' => 'Transition centre status ACTIVE/INACTIVE/CLOSED (manager+)', 'is_system' => 1],
        ['name' => 'staff.view',               'display_name' => 'View Staff',            'module' => 'STAFF',     'description' => 'View staff list and details (scoped)', 'is_system' => 1],
        ['name' => 'staff.manage',             'display_name' => 'Manage Staff',          'module' => 'STAFF',     'description' => 'Create, update, assign centre, activate/deactivate staff (scoped)', 'is_system' => 1],
        ['name' => 'districts.manage',         'display_name' => 'Manage Districts',      'module' => 'DISTRICTS', 'description' => 'Manage district records (super admin only; read-only this phase)', 'is_system' => 1],
        // PHASE 09 — SLOTS
        ['name' => 'slots.view',               'display_name' => 'View Slots',            'module' => 'SLOTS',     'description' => 'View slots (public bookable list; admin list)', 'is_system' => 1],
        ['name' => 'slots.manage',             'display_name' => 'Manage Slots',          'module' => 'SLOTS',     'description' => 'Create, update, delete and generate slots', 'is_system' => 1],
        ['name' => 'slots.cancel',             'display_name' => 'Cancel Slots',          'module' => 'SLOTS',     'description' => 'Cancel slot availability', 'is_system' => 1],
        // PHASE 10 — BOOKINGS + TOKENS + CROPS
        ['name' => 'bookings.create',          'display_name' => 'Create Bookings',       'module' => 'BOOKINGS',  'description' => 'Create own procurement bookings (farmer)', 'is_system' => 1],
        ['name' => 'bookings.view_own',        'display_name' => 'View Own Bookings',     'module' => 'BOOKINGS',  'description' => 'View and cancel own bookings (farmer)', 'is_system' => 1],
        ['name' => 'bookings.view_any',        'display_name' => 'View Any Bookings',     'module' => 'BOOKINGS',  'description' => 'View bookings within scope (staff)', 'is_system' => 1],
        ['name' => 'bookings.cancel_any',      'display_name' => 'Cancel Any Bookings',   'module' => 'BOOKINGS',  'description' => 'Cancel bookings within scope (staff, reason required)', 'is_system' => 1],
        ['name' => 'tokens.view_own',          'display_name' => 'View Own Token',        'module' => 'TOKENS',    'description' => 'View current active token (farmer)', 'is_system' => 1],
        ['name' => 'tokens.view_any',          'display_name' => 'View Tokens',           'module' => 'TOKENS',    'description' => 'View token details within scope (staff)', 'is_system' => 1],
        ['name' => 'crops.view',               'display_name' => 'View Crop Catalog',     'module' => 'CROPS',     'description' => 'View the public bookable crop catalog', 'is_system' => 1],
        // PHASE 11 — QUEUE ENGINE + LIVE QUEUE
        ['name' => 'queue.view',               'display_name' => 'View Queue',            'module' => 'QUEUE',     'description' => 'View operator queue list and live queue aggregate (no PII for live)', 'is_system' => 1],
        ['name' => 'queue.view_own',           'display_name' => 'View Own Queue',        'module' => 'QUEUE',     'description' => 'View own queue position and token status (farmer)', 'is_system' => 1],
        ['name' => 'queue.call_next',          'display_name' => 'Call Next',             'module' => 'QUEUE',     'description' => 'Call the next waiting farmer (atomic)', 'is_system' => 1],
        ['name' => 'queue.skip',               'display_name' => 'Skip Entry',            'module' => 'QUEUE',     'description' => 'Mark a called entry as skipped with reason', 'is_system' => 1],
        ['name' => 'queue.no_show',            'display_name' => 'Mark No-Show',          'module' => 'QUEUE',     'description' => 'Mark a called entry as no-show after the grace period', 'is_system' => 1],
        ['name' => 'queue.stats',              'display_name' => 'View Queue Stats',      'module' => 'QUEUE',     'description' => 'View operator queue statistics for the day', 'is_system' => 1],
        // PHASE 12 — PROCUREMENT + QC + APPROVAL
        ['name' => 'procurements.create',      'display_name' => 'Create Procurements',   'module' => 'PROCUREMENT', 'description' => 'Start procurement processing for a called queue entry (operator)', 'is_system' => 1],
        ['name' => 'procurements.update',      'display_name' => 'Update Procurements',   'module' => 'PROCUREMENT', 'description' => 'Capture weights/QC and submit procurements (operator, own centre)', 'is_system' => 1],
        ['name' => 'procurements.reject',      'display_name' => 'Reject Procurements',   'module' => 'PROCUREMENT', 'description' => 'Reject a pending procurement with reason (operator, own centre)', 'is_system' => 1],
        ['name' => 'procurements.approve',     'display_name' => 'Approve Procurements',  'module' => 'PROCUREMENT', 'description' => 'Approve or reject pending-approval procurements (manager+)', 'is_system' => 1],
        ['name' => 'procurements.view_any',    'display_name' => 'View Any Procurements', 'module' => 'PROCUREMENT', 'description' => 'View procurement records within scope (staff)', 'is_system' => 1],
        ['name' => 'procurements.view_own',    'display_name' => 'View Own Procurements', 'module' => 'PROCUREMENT', 'description' => 'View own procurement records and status (farmer)', 'is_system' => 1],
        // PHASE 13 — PAYMENTS + RATES
        ['name' => 'payments.view',            'display_name' => 'View Payments',         'module' => 'PAYMENTS', 'description' => 'View payment records within scope (staff)', 'is_system' => 1],
        ['name' => 'payments.release',         'display_name' => 'Release Payments',      'module' => 'PAYMENTS', 'description' => 'Release payments with reference (manager+)', 'is_system' => 1],
        ['name' => 'payments.cancel',          'display_name' => 'Cancel Payments',       'module' => 'PAYMENTS', 'description' => 'Cancel pending/initiated payments (manager+)', 'is_system' => 1],
        ['name' => 'payments.reverse',         'display_name' => 'Reverse Payments',      'module' => 'PAYMENTS', 'description' => 'Reverse released payments (district+)', 'is_system' => 1],
        ['name' => 'rates.manage',             'display_name' => 'Manage Crop Rates',     'module' => 'PAYMENTS', 'description' => 'Create and update crop base/override rates (district+)', 'is_system' => 1],
        // PHASE 14 — NOTIFICATIONS
        ['name' => 'notifications.view',       'display_name' => 'View Notifications',    'module' => 'NOTIFICATIONS', 'description' => 'View notification logs and summary (admin)', 'is_system' => 1],
        ['name' => 'notifications.test',       'display_name' => 'Test Push Notification', 'module' => 'NOTIFICATIONS', 'description' => 'Send test push notifications (admin/manager)', 'is_system' => 1],
    ];

    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (name, display_name, module, description, is_system) VALUES (?, ?, ?, ?, 1)");

    foreach ($permissions as $perm) {
        $stmt->execute([$perm['name'], $perm['display_name'], $perm['module'], $perm['description']]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
