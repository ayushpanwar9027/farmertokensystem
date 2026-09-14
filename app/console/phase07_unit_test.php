<?php

declare(strict_types=1);

// Phase 07 unit tests — language system + file manager services.
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;
use App\Exceptions\FileException;
use App\Models\File;
use App\Services\FileService;
use App\Services\LocalizationService;
use App\Services\TranslationService;

$pass = 0;
$fail = 0;

function check(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS: $label" . PHP_EOL; }
    else { $fail++; echo "  FAIL: $label" . PHP_EOL; }
}

function expectExceptionCode(callable $fn): ?string
{
    try {
        $fn();
    } catch (\App\Exceptions\AppException $e) {
        return $e->getErrorCode();
    } catch (\Throwable $e) {
        return get_class($e);
    }
    return null;
}

function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

$purpose = (string) ($argv[1] ?? '');
if ($purpose === 'cleanup-only') {
    echo "Cleanup mode: placeholder\n";
    exit(0);
}

echo "== Phase 07 Unit Tests ==\n";

echo "== LocalizationService ==\n";
$loc = new LocalizationService();
check('default locale is en', $loc->defaultLocale() === 'en');
check('en supported', $loc->isSupported('en'));
check('hi supported', $loc->isSupported('hi'));
check('xx unsupported', !$loc->isSupported('xx'));
check('normalize EN-IN -> en', $loc->normalize('en-IN') === 'en');
check('normalize upper HI -> hi', $loc->normalize('HI') === 'hi');
check('preferredLocale honours X-Locale over Accept-Language', $loc->preferredLocale('hi', 'en', null) === 'hi');
check('preferredLocale falls back to Accept-Language', $loc->preferredLocale(null, 'hi;q=0.9,en;q=0.5', null) === 'hi');
check('preferredLocale defaults when nothing matches', $loc->preferredLocale(null, null, null) === 'en');

echo "== TranslationService ==";
$ts = new TranslationService();
$enPack = $ts->packFor('en');
$hiPack = $ts->packFor('hi');
check('en pack has app.name', isset($enPack['app.name']));
check('en pack includes full key set', count($enPack) >= 60);
check('hi pack has Hindi app.name', ($hiPack['app.name'] ?? '') === 'किसान खरीद प्रणाली');
check('hi pack fills missing key from en fallback', ($hiPack['internal.admin.tooltip'] ?? '') === 'For administrators only');
check('translate hi -> Hindi', $ts->translate('hi', 'app.name') === 'किसान खरीद प्रणाली');
check('translate hi missing -> en fallback', $ts->translate('hi', 'internal.admin.tooltip') === 'For administrators only');
check('translate unknown key returns the key itself', $ts->translate('hi', 'unknown.key.xyz') === 'unknown.key.xyz');
check('languages list has en+hi', count($ts->languages()) >= 2);

if (Database::testConnection()) {
    $beforeHi = (new \App\Models\Translation())->countByLocale('hi');
    $upsert = $ts->bulkUpsert('hi', ['unit.test.key' => 'यूनिट टेस्ट'], 20);
    check('bulkUpsert hi added key', $upsert['updated'] === 1 && $upsert['invalid'] === 0);
    $afterHi = (new \App\Models\Translation())->countByLocale('hi');
    check('hi count grew by 1', $afterHi === $beforeHi + 1);
    $packReloaded = $ts->packFor('hi');
    check('cache invalidated after write (new key visible immediately)', ($packReloaded['unit.test.key'] ?? '') === 'यूनिट टेस्ट');
    Database::delete('translations', 'translation_key = ? AND language_id = (SELECT id FROM languages WHERE code = ? LIMIT 1)', ['unit.test.key', 'hi']);
    $ts->invalidate();
    check('test key removed + pack refreshed', !isset($ts->packFor('hi')['unit.test.key']));

    $bad = $ts->bulkUpsert('hi', ['.bad/key!' => 'x', '' => 'y'], 20);
    check('bulkUpsert rejects malformed keys', $bad['invalid'] >= 1);
    $empty = $ts->bulkUpsert('hi', ['app.name' => '   '], 20);
    check('bulkUpsert skips blank values', $empty['skipped'] >= 1);
} else {
    echo "  SKIP: DB-dependent translation write tests (no DB)\n";
}

echo "== FileService ==";
$fs = new FileService();
check('default allowed extensions include webp+csv+xlsx', in_array('webp', $fs->allowedExtensions(), true) && in_array('xlsx', $fs->allowedExtensions(), true));
check('max upload size is 5MB', $fs->maxSize() === 5242880);

$cleanupFiles = [];

if (Database::testConnection()) {
    $tmpDir = sys_get_temp_dir();

    $phpPath = $tmpDir . '/p07_shell.php';
    file_put_contents($phpPath, '<?php echo "x";');
    $phpUpload = [
        'name' => 'shell.php',
        'tmp_name' => $phpPath,
        'size' => filesize($phpPath),
        'error' => UPLOAD_ERR_OK,
    ];
    check('rejects .php upload -> INVALID_MIME', expectExceptionCode(fn() => $fs->storeUpload($phpUpload, [], 20)) === 'INVALID_MIME');
    @unlink($phpPath);

    $jpgUpload = [
        'name' => 'notreally.jpg',
        'tmp_name' => $phpPath,
        'size' => 5,
        'error' => UPLOAD_ERR_OK,
    ];
    file_put_contents($jpgUpload['tmp_name'], 'plain text, not an image');
    check('rejects fake jpg (mime mismatch) -> INVALID_MIME', expectExceptionCode(fn() => $fs->storeUpload($jpgUpload, [], 20)) === 'INVALID_MIME');
    @unlink($jpgUpload['tmp_name']);

    $sizeLimitPath = $tmpDir . '/p07_tiny.png';
    file_put_contents($sizeLimitPath, tinyPng());
    $sizeUpload = [
        'name' => 'tiny.png',
        'tmp_name' => $sizeLimitPath,
        'size' => filesize($sizeLimitPath),
        'error' => UPLOAD_ERR_OK,
    ];
    putenv('FILE_UPLOAD_MAX_SIZE=40');
    check('rejects oversized upload -> FILE_TOO_LARGE', expectExceptionCode(fn() => $fs->storeUpload($sizeUpload, [], 20)) === 'FILE_TOO_LARGE');
    putenv('FILE_UPLOAD_MAX_SIZE=5242880');
    @unlink($sizeLimitPath);

    $okPng = $tmpDir . '/p07_ok.png';
    file_put_contents($okPng, tinyPng());
    $okUpload = [
        'name' => 'accepted.png',
        'tmp_name' => $okPng,
        'size' => filesize($okPng),
        'error' => UPLOAD_ERR_OK,
    ];
    $uploaded = $fs->storeUpload($okUpload, ['scope' => 'user'], 20);
    $cleanupFiles[] = $uploaded['id'];
    check('png upload succeeds', ($uploaded['file_name'] ?? '') === 'accepted.png');
    check('stored path is relative with uuid name (no absolute web path)', preg_match('#^private/files/[0-9a-f]{32}\.png$#', (string) $uploaded['download_url'], $m) || (($uploaded['url'] ?? '') !== '' && str_contains((string) $uploaded['url'], '/api/v1/files/')));
    $row = (new File())->find($uploaded['id']);
    check('DB row has scope=user and uuid-relative path', $row !== null && $row['scope'] === 'user' && preg_match('#^private/files/[0-9a-f]{32}\.png$#', (string) $row['path_or_url']) === 1);
    check('DB row stores mime image/png', $row !== null && $row['mime_type'] === 'image/png');

    $owned = $fs->list(['id' => 20, 'role' => 'SUPER_ADMIN', 'is_super_admin' => 1, 'role_id' => 1], ['per_page' => 100]);
    check('file list shows uploaded file', count($owned['items']) >= 1 && in_array($uploaded['id'], array_column($owned['items'], 'id'), true));

    $folder = $fs->createFolder(['id' => 20, 'role' => 'SUPER_ADMIN', 'is_super_admin' => 1, 'role_id' => 1], 'p07-unit-folder', null, 'user');
    check('createFolder works', isset($folder['id']) && $folder['name'] === 'p07-unit-folder' && $folder['scope'] === 'user');
    Database::delete('file_folders', 'id = ?', [$folder['id']]);

    $del = $fs->delete(['id' => 20, 'role' => 'SUPER_ADMIN', 'is_super_admin' => 1, 'role_id' => 1], $uploaded['id'], false);
    check('delete (non-force) succeeds', $del['deleted'] === true);
    $deletedRow = Database::selectOne("SELECT * FROM files WHERE id = ?", [$uploaded['id']]);
    check('delete soft-deletes row', $deletedRow !== null && $deletedRow['status'] === 'DELETED' && $deletedRow['deleted_at'] !== null);
    check('delete removes physical file', !file_exists(base_path('storage/app/private/files/' . basename((string) $deletedRow['path_or_url']))));

    $missing = expectExceptionCode(fn() => $fs->delete(['id' => 20, 'role' => 'SUPER_ADMIN', 'is_super_admin' => 1, 'role_id' => 1], 999999, false));
    check('delete missing id -> FILE_NOT_FOUND', $missing === 'FILE_NOT_FOUND');

    Database::delete('file_references', 'file_id = ?', [$uploaded['id']]);
    Database::delete('files', 'id = ?', [$uploaded['id']]);
} else {
    echo "  SKIP: DB-dependent file tests (no DB)\n";
}

echo "\nPASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);