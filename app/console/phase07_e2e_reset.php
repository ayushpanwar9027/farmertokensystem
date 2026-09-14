<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

$ctxPath = base_path('storage/phase07_e2e_context.json');
$cleanup = [];
if (file_exists($ctxPath)) {
    $ctx = json_decode((string) file_get_contents($ctxPath), true);
    $cleanup = is_array($ctx) && isset($ctx['run_cleanup']) ? $ctx['run_cleanup'] : [];
}

foreach (['fa_file', 'fb_file', 'cm_file'] as $key) {
    $id = (int) ($cleanup[$key] ?? 0);
    if ($id <= 0) { continue; }
    $row = Database::selectOne("SELECT path_or_url FROM files WHERE id = ?", [$id]);
    if ($row !== null) {
        Database::delete('file_references', 'file_id = ?', [$id]);
        Database::delete('files', 'id = ?', [$id]);
        $path = base_path('storage/app/private/files/' . basename((string) $row['path_or_url']));
        if (file_exists($path)) { @unlink($path); }
    }
}

foreach (['8123456781', '8123456782', '8123456783', '8123456784'] as $mobile) {
    $user = Database::selectOne("SELECT id FROM users WHERE mobile = ?", [$mobile]);
    if ($user === null) { continue; }
    $uid = (int) $user['id'];
    Database::delete('file_folders', 'created_by = ?', [$uid]);
    Database::delete('centre_staff', 'user_id = ?', [$uid]);
    Database::delete('farmers', 'user_id = ?', [$uid]);
    Database::delete('files', 'created_by = ?', [$uid]);
    Database::delete('user_sessions', 'user_id = ?', [$uid]);
    Database::delete('users', 'id = ?', [$uid]);
}

Database::delete('procurement_centres', "code = 'P07C1'");
Database::delete('file_folders', "name LIKE 'P07 %' OR name LIKE 'Test %'");

foreach (glob(base_path('storage/app/private/files/*')) as $p) {
    if (is_file($p)) {
        $mtime = filemtime($p);
        if ($mtime !== false && time() - $mtime < 600) { @unlink($p); }
    }
}

@unlink($ctxPath);

echo "phase07 reset complete\n";