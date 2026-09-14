<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\AuditService;
use App\Services\FileService;
use App\Services\RbacService;

class FileManagerController
{
    private FileService $files;
    private RbacService $rbac;
    private AuditService $audit;

    public function __construct()
    {
        $this->files = new FileService();
        $this->rbac = new RbacService();
        $this->audit = new AuditService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.manage_all', 'You do not have permission to manage files');

        Response::success($this->files->list($actor, [
            'folder_id' => $request->query('folder_id'),
            'scope' => $request->query('scope'),
            'q' => $request->query('q'),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ]));
    }

    public function destroy(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.manage_all', 'You do not have permission to manage files');

        $id = (int) $request->getParam('id', 0);
        $force = (string) $request->query('force', '1') === '1';

        $result = $this->files->delete($actor, $id, $force);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'FILES_FORCE_DELETED',
            'module' => 'FILES',
            'entity_type' => 'file',
            'entity_id' => $id,
            'new_value' => ['force' => $force],
            'reason' => 'admin_file_force_delete',
        ]);

        Response::success($result);
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