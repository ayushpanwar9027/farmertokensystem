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

class SettingController
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

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'settings.view', 'You do not have permission to view system settings');

        Response::success([
            'groups' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'settings.manage', 'You do not have permission to modify system settings');

        $data = $request->all();
        $values = $data['settings'] ?? null;
        if (!is_array($values) || empty($values)) {
            throw new ValidationException(['settings' => ['At least one setting must be provided']]);
        }

        $updated = $this->settings->setMany(array_filter($values, fn($v) => $v !== null), (int) $actor['id']);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'SETTINGS_UPDATED',
            'module' => 'SETTINGS',
            'entity_type' => 'system_settings',
            'entity_id' => null,
            'new_value' => ['keys' => array_keys($updated), 'values' => $this->maskSensitive($updated)],
            'reason' => 'admin_settings_update',
        ]);

        Response::success([
            'updated' => $updated,
            'message' => 'Settings updated',
        ]);
    }

    private function maskSensitive(array $updated): array
    {
        $masked = [];
        foreach ($updated as $key => $value) {
            $schema = $this->settings->schema($key);
            $masked[$key] = !empty($schema['sensitive']) ? '********' : $value;
        }
        return $masked;
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