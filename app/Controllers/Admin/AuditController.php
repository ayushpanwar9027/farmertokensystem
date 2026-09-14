<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Models\AuditLog;
use App\Services\ScopeService;

class AuditController
{
    private AuditLog $auditLog;
    private ScopeService $scope;

    public function __construct()
    {
        $this->auditLog = new AuditLog();
        $this->scope = new ScopeService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $filters = $request->only(['action', 'module', 'entity_type', 'date_from', 'date_to', 'user_id', 'q']);
        $userIdFilter = $this->scopedUserIds($actor);

        $result = $this->auditLog->adminList($filters, $userIdFilter, $page, $perPage);

        Response::success($result['data'], ['pagination' => $result['pagination']]);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $row = $this->auditLog->find((int) $request->getParam('id'));
        if ($row === null) {
            throw new NotFoundException('AUDIT_NOT_FOUND', 'Audit log entry not found');
        }

        $userIdFilter = $this->scopedUserIds($actor);
        if ($userIdFilter !== null && !in_array((int) $row['user_id'], $userIdFilter, true)) {
            throw new NotFoundException('AUDIT_NOT_FOUND', 'Audit log entry not found');
        }

        Response::success(['audit' => $row]);
    }

    private function scopedUserIds(array $actor): ?array
    {
        if (($actor['is_super_admin'] ?? 0) === 1 && ($actor['role'] ?? '') === 'SUPER_ADMIN') {
            return null;
        }

        $centreIds = $this->scope->centreIdsFor($actor);
        $districtIds = $this->scope->districtIdsFor($actor);

        return $this->auditLog->userIdsForRoleLevel($actor, $centreIds, $districtIds);
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