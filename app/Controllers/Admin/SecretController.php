<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Services\AuditService;
use App\Services\RbacService;
use App\Services\SecretService;

class SecretController
{
    private SecretService $secrets;
    private AuditService $audit;
    private RbacService $rbac;

    public function __construct()
    {
        $this->secrets = new SecretService();
        $this->audit = new AuditService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'settings.secret_manage', 'You do not have permission to view secrets');

        Response::success([
            'secrets' => $this->secrets->list(),
        ]);
    }

    public function update(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'settings.secret_manage', 'You do not have permission to manage secrets');

        $data = $request->all();
        $key = trim((string) ($data['key'] ?? ''));
        $value = $data['value'] ?? null;

        $errors = [];
        if ($key === '') {
            $errors['key'][] = 'Key is required';
        }
        if (!is_string($value) || trim($value) === '') {
            $errors['value'][] = 'Value is required';
        }
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
        if (!$this->secrets->isRegistered($key)) {
            throw new ValidationException(['key' => ['Unknown secret key: ' . $key]]);
        }

        $masked = $this->secrets->set($key, $value, (int) $actor['id']);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'SECRET_UPDATED',
            'module' => 'SECRETS',
            'entity_type' => 'system_secret',
            'entity_id' => null,
            'new_value' => ['key' => $key, 'last4' => $masked['last4']],
            'reason' => 'admin_secret_rotation',
        ]);

        Response::success([
            'secret' => $masked,
            'message' => 'Secret stored',
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