<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

Database::update('system_settings', ['key_value' => '0', 'updated_at' => date('Y-m-d H:i:s')], "key_name = 'maintenance_mode'", []);
Database::update('system_settings', ['key_value' => '', 'updated_at' => date('Y-m-d H:i:s')], "key_name = 'maintenance_message'", []);
Database::update('system_settings', ['key_value' => '', 'updated_at' => date('Y-m-d H:i:s')], "key_name = 'maintenance_expected_available_at'", []);
Database::update('system_settings', ['key_value' => 'Farmer Procurement System', 'updated_at' => date('Y-m-d H:i:s')], "key_name = 'system_name'", []);
Database::update('system_settings', ['key_value' => '30', 'updated_at' => date('Y-m-d H:i:s')], "key_name = 'session_timeout_minutes'", []);
Database::update('system_settings', ['key_value' => '1', 'updated_at' => date('Y-m-d H:i:s')], "key_name = 'sms_enabled'", []);

foreach (['otp_api_key', 'jwt_secret'] as $key) {
    Database::update(
        'system_secrets',
        [
            'encrypted_value' => base64_encode('PLACEHOLDER_NOT_SET'),
            'iv' => base64_encode('0000000000000000'),
            'is_set' => 0,
            'last_rotated_at' => null,
            'last_updated_by' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        'key_name = ?',
        [$key]
    );
}

(new \App\Services\SettingService())->invalidateCache();

echo "state reset\n";