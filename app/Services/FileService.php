<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\FileException;
use App\Exceptions\NotFoundException;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileReference;

class FileService
{
    private const SCOPES = ['system', 'user', 'centre'];

    private RbacService $rbac;
    private ScopeService $scope;

    public function __construct()
    {
        $this->rbac = new RbacService();
        $this->scope = new ScopeService($this->rbac);
    }

    public function rootDirectory(): string
    {
        return (string) config('files.disk.root', storage_path('app/private/files'));
    }

    public function relativeUploadBase(): string
    {
        return 'private/files';
    }

    public function maxSize(): int
    {
        $configLimit = (int) config('files.max_size', 5242880);
        $settingLimitMb = (int) get_setting('file_upload_max_size_mb', 0);
        if ($settingLimitMb > 0) {
            $settingBytes = $settingLimitMb * 1024 * 1024;
            return max(1, min($configLimit, $settingBytes));
        }
        return $configLimit;
    }

    public function allowedExtensions(): array
    {
        $extensions = (array) config('files.allowed_extensions', []);
        if (empty($extensions)) {
            $extensions = ['jpg', 'png', 'webp', 'pdf', 'csv', 'xlsx'];
        }
        return array_values(array_unique(array_map('strtolower', $extensions)));
    }

    public function isStaff(array $user): bool
    {
        return strtoupper((string) ($user['role'] ?? '')) !== 'FARMER';
    }

    public function storeUpload(array $upload, array $meta, int $userId): array
    {
        $this->validateUpload($upload);

        $originalName = $this->sanitizeFileName($upload['name'] ?? '');
        if ($originalName === '') {
            throw new FileException('INVALID_MIME', 'Invalid file name', 400);
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions(), true)) {
            throw new FileException('INVALID_MIME', 'File type not allowed', 400, [
                'allowed' => $this->allowedExtensions(),
                'received' => $extension !== '' ? $extension : null,
            ]);
        }

        $detectedMime = $this->detectMime($upload['tmp_name']);

        $acceptedMimes = (array) (config('files.mime_map.' . $extension, []));
        if (!in_array($detectedMime, $acceptedMimes, true)) {
            throw new FileException('INVALID_MIME', 'File content does not match the allowed type', 400, [
                'expected' => $acceptedMimes,
                'detected' => $detectedMime,
            ]);
        }

        $this->assertSafeMime($detectedMime);

        $relativePath = $this->persist($upload['tmp_name'], $extension);

        $scope = in_array($meta['scope'] ?? 'user', self::SCOPES, true) ? $meta['scope'] : 'user';

        $folder = null;
        $folderId = isset($meta['folder_id']) && $meta['folder_id'] !== '' ? (int) $meta['folder_id'] : null;

        if ($folderId !== null && $folderId > 0) {
            $folder = (new FileFolder())->find($folderId);
            if ($folder === null) {
                throw new NotFoundException('FOLDER_NOT_FOUND', 'Folder not found', 404);
            }
            if (in_array($folder['scope'], ['system', 'centre'], true)) {
                $scope = $folder['scope'];
            }
        }

        $fileId = (new File())->insert([
            'file_name' => $originalName,
            'source_type' => 'LOCAL',
            'path_or_url' => $this->relativeUploadBase() . '/' . basename($relativePath),
            'folder_id' => $folderId,
            'mime_type' => $detectedMime,
            'size' => (int) $upload['size'],
            'extension' => $extension,
            'checksum' => $this->checksum($relativePath),
            'created_by' => $userId,
            'status' => 'ACTIVE',
            'scope' => $scope,
        ]);

        $reference = null;
        $sourceType = $meta['entity_category'] ?? null;
        if (is_string($sourceType) && $sourceType !== '') {
            $sourceId = isset($meta['entity_id']) && $meta['entity_id'] !== '' ? (int) $meta['entity_id'] : null;
            $reference = (new FileReference())->insert([
                'file_id' => $fileId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);
        }

        $record = (new File())->find($fileId);
        $result = $this->present($record);
        $result['reference'] = $reference !== null ? [
            'source_type' => $sourceType,
            'source_id' => isset($meta['entity_id']) && $meta['entity_id'] !== '' ? (int) $meta['entity_id'] : null,
        ] : null;

        return $result;
    }

    public function get(int $id): ?array
    {
        return (new File())->find($id);
    }

    public function canAccess(?array $user, array $file): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->rbac->isSuperAdmin($user)) {
            return true;
        }

        $scope = (string) ($file['scope'] ?? 'user');

        if ($scope === 'system') {
            return $this->isStaff($user);
        }

        if ($scope === 'user') {
            return (int) ($file['created_by'] ?? 0) === (int) $user['id'];
        }

        if ($scope === 'centre') {
            $centreIds = $this->centreIdsForFile((int) $file['id']);
            if (empty($centreIds)) {
                return false;
            }
            foreach ($centreIds as $centreId) {
                if ($this->scope->canAccessCentre($user, $centreId)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    public function assertAccess(?array $user, array $file): void
    {
        if (!$this->canAccess($user, $file)) {
            throw new AuthorizationException('You do not have access to this file');
        }
    }

    public function list(array $user, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        $where = ["f.deleted_at IS NULL", "f.status = 'ACTIVE'"];
        $params = [];

        if (!empty($filters['folder_id'])) {
            $where[] = "f.folder_id = ?";
            $params[] = (int) $filters['folder_id'];
        }

        if (!empty($filters['scope']) && in_array($filters['scope'], self::SCOPES, true)) {
            $where[] = "f.scope = ?";
            $params[] = $filters['scope'];
        }

        if (!empty($filters['q'])) {
            $where[] = "f.file_name LIKE ?";
            $params[] = '%' . $filters['q'] . '%';
        }

        $this->applyVisibilityClause($where, $params, $user);

        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne("SELECT COUNT(*) AS c FROM files f WHERE {$whereSql}", $params);
        $total = $totalRow !== null ? (int) $totalRow['c'] : 0;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT f.*,
                    (SELECT ff.name FROM file_folders ff WHERE ff.id = f.folder_id AND ff.deleted_at IS NULL) AS folder_name
             FROM files f
             WHERE {$whereSql}
             ORDER BY f.created_at DESC, f.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => array_map(fn($row) => $this->present($row), $rows),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ];
    }

    public function listFolders(array $user, array $filters = []): array
    {
        $where = ["deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['scope']) && in_array($filters['scope'], self::SCOPES, true)) {
            $where[] = "scope = ?";
            $params[] = $filters['scope'];
        }

        if (!$this->rbac->isSuperAdmin($user)) {
            $clauses = [];
            if ($this->isStaff($user)) {
                $clauses[] = "scope IN ('system', 'centre')";
            }
            $clauses[] = "created_by = ?";
            $params[] = (int) $user['id'];
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        $whereSql = implode(' AND ', $where);

        return Database::select(
            "SELECT id, name, parent_id, scope, created_by, created_at
             FROM file_folders
             WHERE {$whereSql}
             ORDER BY name ASC",
            $params
        );
    }

    public function createFolder(array $user, string $name, ?int $parentId, string $scope): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 190) {
            throw new \App\Exceptions\ValidationException(['name' => ['Folder name is required (max 190 characters)']]);
        }

        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'user';
        if (in_array($scope, ['system', 'centre'], true) && !$this->isStaff($user)) {
            throw new AuthorizationException('Farmers can only create personal folders');
        }

        if ($parentId !== null && $parentId > 0) {
            $parent = (new FileFolder())->find($parentId);
            if ($parent === null) {
                throw new NotFoundException('FOLDER_NOT_FOUND', 'Parent folder not found', 404);
            }
        }

        $folderId = (new FileFolder())->insert([
            'name' => $name,
            'parent_id' => $parentId !== null && $parentId > 0 ? $parentId : null,
            'scope' => $scope,
            'created_by' => (int) $user['id'],
        ]);

        $folder = (new FileFolder())->find($folderId);

        return [
            'id' => (int) $folder['id'],
            'name' => $folder['name'],
            'parent_id' => $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null,
            'scope' => $folder['scope'],
            'created_by' => (int) $folder['created_by'],
            'created_at' => $folder['created_at'],
        ];
    }

    public function delete(array $user, int $id, bool $force = false): array
    {
        $file = $this->get($id);
        if ($file === null) {
            throw new NotFoundException('FILE_NOT_FOUND', 'File not found', 404);
        }

        $this->assertAccess($user, $file);

        if ($force) {
            if (!$this->rbac->isSuperAdmin($user) && !$this->isStaff($user)) {
                throw new AuthorizationException('Only staff can force-delete files');
            }
        }

        $references = (new FileReference())->forFile($id);
        if (!empty($references) && !$force) {
            throw new FileException('FILE_IN_USE', 'File is referenced and cannot be deleted', 409, [
                'references' => count($references),
            ]);
        }

        $this->RemovePhysical($file);

        if ($force) {
            (new File())->hardDelete($id);
        } else {
            Database::update('files', ['status' => 'DELETED'], 'id = ?', [$id]);
            (new File())->delete($id);
        }

        return [
            'id' => $id,
            'force' => $force,
            'deleted' => true,
        ];
    }

    public function present(array $file): array
    {
        $extension = strtolower((string) ($file['extension'] ?? ''));
        $inlineExtensions = (array) config('files.inline_extensions', []);

        return [
            'id' => (int) $file['id'],
            'file_name' => $file['file_name'],
            'folder_id' => $file['folder_id'] !== null ? (int) $file['folder_id'] : null,
            'folder_name' => $file['folder_name'] ?? null,
            'scope' => $file['scope'] ?? 'user',
            'mime_type' => $file['mime_type'],
            'size' => (int) $file['size'],
            'size_human' => $this->humanSize((int) $file['size']),
            'extension' => $extension,
            'checksum' => $file['checksum'],
            'created_by' => $file['created_by'] !== null ? (int) $file['created_by'] : null,
            'created_at' => $file['created_at'],
            'can_inline' => in_array($extension, $inlineExtensions, true),
            'url' => '/api/v1/files/' . (int) $file['id'],
            'download_url' => '/api/v1/files/' . (int) $file['id'] . '?download=1',
        ];
    }

    public function physicalPath(array $file): ?string
    {
        $rel = (string) ($file['path_or_url'] ?? '');
        if ($rel === '') {
            return null;
        }
        $path = $this->rootDirectory() . DIRECTORY_SEPARATOR . basename($rel);
        return is_file($path) ? $path : null;
    }

    public function centreIdsForFile(int $fileId): array
    {
        $rows = Database::select(
            "SELECT source_id FROM file_references
             WHERE file_id = ? AND source_type = 'procurement_centre' AND source_id IS NOT NULL",
            [$fileId]
        );
        return array_values(array_unique(array_map(fn($r) => (int) $r['source_id'], $rows)));
    }

    public function accessibleCentreIds(array $user): array
    {
        if ($this->rbac->isSuperAdmin($user)) {
            return [];
        }

        $userScope = $this->scope->scopeFor($user);

        if ($userScope['type'] === 'all') {
            return [];
        }

        if ($userScope['type'] === 'district') {
            $districtIds = $userScope['district_ids'];
            if (empty($districtIds)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($districtIds), '?'));
            $rows = Database::select(
                "SELECT id FROM procurement_centres WHERE district_id IN ({$placeholders}) AND deleted_at IS NULL",
                $districtIds
            );
            return array_map(fn($r) => (int) $r['id'], $rows);
        }

        if ($userScope['type'] === 'centre') {
            return $userScope['centre_ids'];
        }

        return [];
    }

    public function applyVisibilityClause(array &$where, array &$params, array $user): void
    {
        if ($this->rbac->isSuperAdmin($user)) {
            return;
        }

        $clauses = [];
        $own = (int) $user['id'];
        $isStaff = $this->isStaff($user);

        if ($isStaff) {
            $clauses[] = "f.scope IN ('system', 'centre')";
        }
        $clauses[] = "f.scope = 'user' AND f.created_by = ?";
        $params[] = $own;

        $centreIds = $this->accessibleCentreIds($user);
        if ($isStaff && !empty($centreIds)) {
            $placeholders = implode(',', array_fill(0, count($centreIds), '?'));
            $clauses[] = "f.scope = 'centre' AND EXISTS (
                SELECT 1 FROM file_references r
                WHERE r.file_id = f.id
                  AND r.source_type = 'procurement_centre'
                  AND r.source_id IN ({$placeholders})
            )";
            foreach ($centreIds as $centreId) {
                $params[] = $centreId;
            }
        }

        $where[] = '(' . implode(' OR ', $clauses) . ')';
    }

    public function validateUpload(array $upload): void
    {
        if (empty($upload) || !isset($upload['name'], $upload['tmp_name'], $upload['size'])) {
            throw new FileException('UPLOAD_ERROR', 'No file uploaded', 400);
        }

        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new FileException('FILE_TOO_LARGE', 'The uploaded file exceeds the size limit', 400, [
                    'max_size' => $this->maxSize(),
                ]);
            }
            throw new FileException('UPLOAD_ERROR', 'File upload failed', 400);
        }

        $size = (int) $upload['size'];
        if ($size <= 0) {
            throw new FileException('UPLOAD_ERROR', 'The uploaded file is empty', 400);
        }

        if ($size > $this->maxSize()) {
            throw new FileException('FILE_TOO_LARGE', 'The uploaded file exceeds the size limit', 400, [
                'max_size' => $this->maxSize(),
                'received_size' => $size,
            ]);
        }

        if (!is_file($upload['tmp_name'])) {
            throw new FileException('STORAGE_ERROR', 'Uploaded file is missing on the server', 500);
        }
    }

    private function detectMime(string $tmpPath): string
    {
        if (!class_exists(\finfo::class)) {
            throw new FileException('STORAGE_ERROR', 'File inspection unavailable', 500);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpPath);
        if (!is_string($detected) || $detected === '' || $detected === false) {
            throw new FileException('INVALID_MIME', 'Unable to detect file type', 400);
        }
        return strtolower($detected);
    }

    private function assertSafeMime(string $mime): void
    {
        $blockedPatterns = ['x-php', 'x-httpd-php', 'msdownload', 'x-msdownload', 'x-sh', 'shell', 'executable', 'macbinary'];
        foreach ($blockedPatterns as $needle) {
            if (str_contains($mime, $needle)) {
                throw new FileException('INVALID_MIME', 'File type not allowed', 400);
            }
        }
    }

    private function persist(string $tmpPath, string $extension): string
    {
        $root = $this->rootDirectory();
        if (!is_dir($root)) {
            if (!@mkdir($root, 0755, true) && !is_dir($root)) {
                throw new FileException('STORAGE_ERROR', 'File storage directory is unavailable', 500);
            }
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $root . DIRECTORY_SEPARATOR . $stored;

        if (!@move_uploaded_file($tmpPath, $destination)) {
            if (!@rename($tmpPath, $destination)) {
                throw new FileException('STORAGE_ERROR', 'Could not store the uploaded file', 500);
            }
        }

        return $destination;
    }

    private function RemovePhysical(array $file): void
    {
        $path = $this->physicalPath($file);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    private function checksum(string $absolutePath): string
    {
        $hash = @hash_file('sha256', $absolutePath);
        return is_string($hash) ? $hash : '';
    }

    private function sanitizeFileName(string $name): string
    {
        $name = str_replace(['\\', '/', "\0"], '', (string) $name);
        $name = preg_replace('/[^\x20-\x7E\x80-\xFF]/', '', $name) ?? '';
        return mb_substr(trim($name), 0, 190);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}