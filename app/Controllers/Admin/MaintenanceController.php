<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Services\AuditService;
use App\Services\RbacService;
use App\Services\SettingService;

class MaintenanceController
{
    private SettingService $settings;
    private AuditService $audit;
    private RbacService $rbac;

    public function __construct()
    {
        $this->settings = new SettingService();
        $this->audit = new AuditService();
        $this->rbac = new RbacService();
    }

    public function update(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'maintenance.manage', 'You do not have permission to manage maintenance mode');

        $data = $request->all();
        $enabled = $data['enabled'] ?? null;
        $message = $data['message'] ?? null;
        $expectedAt = $data['expected_available_at'] ?? null;

        $errors = [];
        if ($enabled === null) {
            $errors['enabled'][] = 'enabled is required (true or false)';
        } else {
            $normalized = is_bool($enabled) ? $enabled : (is_string($enabled) ? filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null);
            if ($normalized === null) {
                $errors['enabled'][] = 'enabled must be a boolean';
            }
        }
        if ($message !== null && !is_string($message)) {
            $errors['message'][] = 'message must be a string';
        }
        if ($expectedAt !== null && !is_string($expectedAt)) {
            $errors['expected_available_at'][] = 'expected_available_at must be a string';
        }
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $normalized = is_bool($enabled) ? $enabled : filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $changes = [];

        if ($message !== null) {
            $this->settings->set('maintenance_message', (string) $message, (int) $actor['id']);
            $changes['maintenance_message'] = (string) $message;
        }

        if ($expectedAt !== null) {
            $this->settings->set('maintenance_expected_available_at', (string) $expectedAt, (int) $actor['id']);
            $changes['maintenance_expected_available_at'] = (string) $expectedAt;
        }

        $this->settings->set('maintenance_mode', $normalized, (int) $actor['id']);
        $changes['maintenance_mode'] = $normalized ? '1' : '0';

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => $normalized ? 'MAINTENANCE_ENABLED' : 'MAINTENANCE_DISABLED',
            'module' => 'MAINTENANCE',
            'entity_type' => 'system_settings',
            'entity_id' => null,
            'new_value' => $changes,
            'reason' => 'admin_maintenance_toggle',
        ]);

        Response::success([
            'maintenance' => [
                'enabled' => (bool) $normalized,
                'message' => $this->settings->getString('maintenance_message', ''),
                'expected_available_at' => $this->settings->getString('maintenance_expected_available_at', ''),
            ],
            'message' => $normalized ? 'Maintenance mode enabled' : 'Maintenance mode disabled',
        ]);
    }

    private function requireActor(Request $request): array
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthorizationException('Authentication required');
        }
        return $user;
    }
}