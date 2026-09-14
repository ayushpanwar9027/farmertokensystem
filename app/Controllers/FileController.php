<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\FileException;
use App\Exceptions\NotFoundException;
use App\Services\AuditService;
use App\Services\FileService;
use App\Services\RbacService;

class FileController
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

    public function upload(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.upload', 'You do not have permission to upload files');

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            throw new FileException('UPLOAD_ERROR', 'No file uploaded (multipart field "file")', 400);
        }

        $meta = $request->only(['folder_id', 'scope', 'entity_category', 'entity_id']);

        $result = $this->files->storeUpload($_FILES['file'], $meta, (int) $actor['id']);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'FILES_UPLOADED',
            'module' => 'FILES',
            'entity_type' => 'file',
            'entity_id' => $result['id'],
            'new_value' => [
                'file_name' => $result['file_name'],
                'mime' => $result['mime_type'],
                'size' => $result['size'],
                'scope' => $result['scope'],
            ],
            'reason' => 'file_upload',
        ]);

        Response::created(['file' => $result]);
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.download', 'You do not have permission to view files');

        Response::success($this->files->list($actor, [
            'folder_id' => $request->query('folder_id'),
            'scope' => $request->query('scope'),
            'q' => $request->query('q'),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ]));
    }

    public function folders(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.folders', 'You do not have permission to view folders');

        Response::success([
            'folders' => $this->files->listFolders($actor, [
                'scope' => $request->query('scope'),
            ]),
        ]);
    }

    public function storeFolder(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.folders', 'You do not have permission to create folders');

        $folder = $this->files->createFolder(
            $actor,
            (string) $request->input('name', ''),
            $request->input('parent_id') !== null ? (int) $request->input('parent_id') : null,
            (string) $request->input('scope', 'user')
        );

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'FOLDER_CREATED',
            'module' => 'FILES',
            'entity_type' => 'file_folder',
            'entity_id' => $folder['id'],
            'new_value' => [
                'name' => $folder['name'],
                'parent_id' => $folder['parent_id'],
                'scope' => $folder['scope'],
            ],
            'reason' => 'folder_create',
        ]);

        Response::created(['folder' => $folder]);
    }

    public function serve(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.download', 'You do not have permission to download files');

        $id = (int) $request->getParam('id', 0);
        $file = $this->files->get($id);
        if ($file === null) {
            throw new NotFoundException('FILE_NOT_FOUND', 'File not found', 404);
        }

        $this->files->assertAccess($actor, $file);

        $path = $this->files->physicalPath($file);
        if ($path === null) {
            throw new FileException('STORAGE_ERROR', 'File content is unavailable', 500);
        }

        $download = (string) $request->query('download', '') === '1';
        $disposition = $download ? 'attachment' : 'inline';

        http_response_code(200);
        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . $this->safeFilename($file['file_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Cache-Control: private, max-age=3600');

        readfile($path);
    }

    public function destroy(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'files.delete', 'You do not have permission to delete files');

        $id = (int) $request->getParam('id', 0);
        $force = (string) $request->query('force', '') === '1';

        $result = $this->files->delete($actor, $id, $force);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'FILES_DELETED',
            'module' => 'FILES',
            'entity_type' => 'file',
            'entity_id' => $id,
            'new_value' => ['force' => $force],
            'reason' => 'file_delete',
        ]);

        Response::success($result);
    }

    private function safeFilename(string $name): string
    {
        $name = str_replace(['"', "\r", "\n"], '', $name);
        if ($name === '') {
            return 'download';
        }
        return mb_strlen($name) > 200 ? mb_substr($name, 0, 200) : $name;
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