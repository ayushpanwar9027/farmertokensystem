<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingService;

class HealthController
{
    public function index(Request $request): void
    {
        $database = Database::testConnection() ? 'OK' : 'DOWN';

        $logsWritable = is_writable_recursive(storage_path('logs'));
        $cacheWritable = is_writable_recursive(storage_path('cache'));
        $storage = ($logsWritable && $cacheWritable) ? 'OK' : 'DOWN';

        Response::success([
            'status' => 'UP',
            'database' => $database,
            'storage' => $storage,
            'version' => getenv('APP_VERSION') ?: '1.0.0',
            'time' => gmdate('c'),
        ]);
    }

    public function maintenance(Request $request): void
    {
        $settings = new SettingService();
        $maintenance = [
            'enabled' => $settings->getBool('maintenance_mode', false),
            'message' => $settings->getString('maintenance_message', 'System off for maintenance. Please check back later.'),
            'expected_available_at' => $settings->getString('maintenance_expected_available_at', ''),
            'support_contact' => [
                'phone' => $settings->getString('support_contact_phone', ''),
                'email' => $settings->getString('support_contact_email', ''),
            ],
        ];

        Response::success($maintenance);
    }
}