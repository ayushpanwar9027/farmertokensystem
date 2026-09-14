<?php

declare(strict_types=1);

// Phase 07 E2E verification via raw HTTP against php -S 127.0.0.1:8090.
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\TranslationService;

$base = 'http://127.0.0.1:8090';
$pass = 0;
$fail = 0;

function check(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS: $label" . PHP_EOL; }
    else { $fail++; echo "  FAIL: $label" . PHP_EOL; }
}

function rawCall(string $method, string $url, ?string $body = null, ?string $token = null, array $extra = [], ?string $contentType = null): array
{
    $headers = [];
    $headers['Content-Type'] = $contentType !== null ? $contentType : 'application/json';
    if ($token !== null && $token !== '') {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    foreach ($extra as $k => $v) {
        $headers[$k] = $v;
    }
    $headerLines = [];
    foreach ($headers as $k => $v) {
        $headerLines[] = "$k: $v";
    }

    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headerLines,
        'ignore_errors' => true,
        'content' => $body,
        'timeout' => 20,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0])) {
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
        $status = is_numeric($m[1] ?? 0) ? (int) $m[1] : 0;
    }
    return ['status' => $status, 'body' => $raw, 'headers' => $http_response_header ?? []];
}

function headerPair(array $headers, string $name): ?string
{
    foreach ($headers as $line) {
        if (stripos($line, $name . ':') === 0) {
            return trim(explode(':', $line, 2)[1] ?? '');
        }
    }
    return null;
}

function jdec($body): array
{
    $d = json_decode((string) $body, true);
    return is_array($d) ? $d : [];
}

function makeJpegContent(): string
{
    $path = tempnam(sys_get_temp_dir(), 'p07') . '.jpg';
    $im = imagecreatetruecolor(6, 6);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $white);
    imagerectangle($im, 1, 1, 4, 4, $black);
    imagejpeg($im, $path, 90);
    imagedestroy($im);
    $content = (string) file_get_contents($path);
    @unlink($path);
    return $content;
}

function mpUpload(string $url, string $token, string $fileName, string $content, array $fields = []): array
{
    $boundary = '----p07' . bin2hex(random_bytes(6));
    $body = '';
    foreach ($fields as $k => $v) {
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
    }
    $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$fileName}\"\r\nContent-Type: application/octet-stream\r\n\r\n{$content}\r\n";
    $body .= "--{$boundary}--\r\n";
    return rawCall('POST', $url, $body, $token, [], 'multipart/form-data; boundary=' . $boundary);
}

$ctxPath = base_path('storage/phase07_e2e_context.json');
$ctx = json_decode((string) file_get_contents($ctxPath), true);
if (!is_array($ctx)) {
    echo "NO CONTEXT — run phase07_e2e_seed.php first\n";
    exit(2);
}

$ctx['centerId'] = (int) $ctx['centre']['id'];
$faFarmerIdRow = Database::selectOne("SELECT id FROM farmers WHERE user_id = ?", [(int) $ctx['fa']['id']]);
$faFarmerId = (int) ($faFarmerIdRow['id'] ?? 0);

echo "== Phase 07 E2E Verification ==\n";

echo "== Health ==";
$h = rawCall('GET', "$base/health");
check('GET /health -> 200', $h['status'] === 200);

echo "== Locale detection ==";
$langsDef = rawCall('GET', "$base/api/v1/languages");
$ld = jdec($langsDef['body']);
check('GET /languages (no headers) -> 200 + default en', $langsDef['status'] === 200 && ($ld['meta']['locale'] ?? '') === 'en');
check('languages list includes en+hi', count($ld['data']['languages'] ?? []) >= 2);

$langsAcc = rawCall('GET', "$base/api/v1/languages", null, null, ['Accept-Language' => 'hi;q=0.9,en;q=0.5']);
$la = jdec($langsAcc['body']);
check('Accept-Language: hi -> locale hi', ($la['meta']['locale'] ?? '') === 'hi');

$langsXl = rawCall('GET', "$base/api/v1/languages", null, null, ['X-Locale' => 'hi']);
$lx = jdec($langsXl['body']);
check('X-Locale: hi -> locale hi', ($lx['meta']['locale'] ?? '') === 'hi');

echo "== Translation packs ==";
$pEn = rawCall('GET', "$base/api/v1/translations/en");
$pe = jdec($pEn['body']);
check('GET /translations/en -> 200', $pEn['status'] === 200);
check('en app.name present', ($pe['data']['translations']['app.name'] ?? '') === 'Farmer Procurement System');

$pHi = rawCall('GET', "$base/api/v1/translations/hi");
$ph = jdec($pHi['body']);
check('GET /translations/hi -> 200', $pHi['status'] === 200);
check('hi app.name is Hindi', ($ph['data']['translations']['app.name'] ?? '') === 'किसान खरीद प्रणाली');
check('hi pack fills missing key from en fallback', ($ph['data']['translations']['internal.admin.tooltip'] ?? '') === 'For administrators only');

$pBad = rawCall('GET', "$base/api/v1/translations/xx");
check('GET /translations/xx -> 404 INVALID_LOCALE', $pBad['status'] === 404 && str_contains($pBad['body'], 'INVALID_LOCALE'));

echo "== Auth ==";
$saLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456781","password":"Sa07Test@1234"}');
$saData = jdec($saLogin['body']);
$saToken = $saData['data']['access_token'] ?? '';
check('SA login -> 200 + token', $saLogin['status'] === 200 && is_string($saToken) && strlen($saToken) > 20);

foreach (['fa', 'fb', 'cm'] as $who) {
    $$who = $ctx[$who]['mobile'];
}
$faLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456782","password":"Fa07Test@1234"}');
$faToken = jdec($faLogin['body'])['data']['access_token'] ?? '';
$fbLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456783","password":"Fb07Test@1234"}');
$fbToken = jdec($fbLogin['body'])['data']['access_token'] ?? '';
$cmLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456784","password":"Cm07Test@1234"}');
$cmToken = jdec($cmLogin['body'])['data']['access_token'] ?? '';
check('FA/FB/CM logins -> 200 + tokens', $faLogin['status'] === 200 && $fbLogin['status'] === 200 && $cmLogin['status'] === 200);

echo "== Admin translations (RBAC + write + cache invalidation) ==";
$adList = rawCall('GET', "$base/api/v1/admin/translations?locale=hi&q=common.save", null, $saToken);
$al = jdec($adList['body']);
check('SA list admin translations (hi) -> 200', $adList['status'] === 200 && count($al['data']['translations']['items'] ?? []) >= 1);

$faList = rawCall('GET', "$base/api/v1/admin/translations?locale=hi", null, $faToken);
check('FA can view admin translations (translations.view) -> 200', $faList['status'] === 200);

$faWrite = rawCall('PUT', "$base/api/v1/admin/languages/hi/translations", '{"translations":{"common.save":"x"}}', $faToken);
check('FA cannot manage translations -> 403', $faWrite['status'] === 403);

$put1 = rawCall('PUT', "$base/api/v1/admin/languages/hi/translations", '{"translations":{"internal.admin.tooltip":"प्रशासक के लिए"}}', $saToken);
check('SA bulk update -> 200 updated>=1', $put1['status'] === 200 && (jdec($put1['body'])['data']['updated'] ?? 0) >= 1);

$peek1 = rawCall('GET', "$base/api/v1/translations/hi");
check('pack reflects write immediately (cache invalidated)', (jdec($peek1['body'])['data']['translations']['internal.admin.tooltip'] ?? '') === 'प्रशासक के लिए');

$del = rawCall('DELETE', "$base/api/v1/admin/languages/hi/translations", '{"key":"internal.admin.tooltip"}', $saToken);
check('SA delete translation key -> 200', $del['status'] === 200 && (jdec($del['body'])['data']['deleted'] ?? false) === true);

$peek2 = rawCall('GET', "$base/api/v1/translations/hi");
check('deleted hi key falls back to en (internal.admin.tooltip = For administrators only)', (jdec($peek2['body'])['data']['translations']['internal.admin.tooltip'] ?? '') === 'For administrators only');

$put2 = rawCall('PUT', "$base/api/v1/admin/languages/hi/translations", '{"translations":{"internal.admin.tooltip":"प्रशासक के लिए"}}', $saToken);
check('SA re-add key -> 200', $put2['status'] === 200);

$peek3 = rawCall('GET', "$base/api/v1/translations/hi");
check('re-added key shows Hindi again', (jdec($peek3['body'])['data']['translations']['internal.admin.tooltip'] ?? '') === 'प्रशासक के लिए');

$del2 = rawCall('DELETE', "$base/api/v1/admin/languages/hi/translations", '{"key":"internal.admin.tooltip"}', $saToken);
check('SA delete key again (cleanup) -> 200', $del2['status'] === 200);

$exp = rawCall('GET', "$base/api/v1/admin/translations/export?locale=h1", null, $saToken);
check('export invalid locale -> 404', $exp['status'] === 404);

echo "== Missing-key logging (service-level) ==";
$ts = new TranslationService();
$unknown = $ts->translate('hi', 'totally.unknown.e2e.key');
check('unknown key falls back to the key itself', $unknown === 'totally.unknown.e2e.key');
$missLog = @file_get_contents(storage_path('logs/translations_missing.log'));
check('missing key is throttled-logged', is_string($missLog) && str_contains($missLog, 'totally.unknown.e2e.key'));

echo "== File uploads (FA) ==";
$jpg = makeJpegContent();
$up1 = mpUpload("$base/api/v1/files/upload", $faToken, 'fa-green.jpg', $jpg, ['scope' => 'user']);
$u1 = jdec($up1['body']);
$faFileId = (int) ($u1['data']['file']['id'] ?? 0);
check('FA jpg upload -> 201', $up1['status'] === 201 && $faFileId > 0);
check('upload response has relative api url only', !str_contains((string) $up1['body'], 'storage/app') && ($u1['data']['file']['url'] ?? '') === "/api/v1/files/{$faFileId}");

if (Database::testConnection() && $faFileId > 0) {
    $row = Database::selectOne("SELECT * FROM files WHERE id = ?", [$faFileId]);
    check('DB stores uuid-relative path (no abs path)', $row !== null && preg_match('#^private/files/[0-9a-f]{32}\.jpg$#', (string) $row['path_or_url']) === 1);
    check('DB row scope=user + image/jpeg', $row !== null && $row['scope'] === 'user' && $row['mime_type'] === 'image/jpeg');
    check('DB row has 64-hex checksum', $row !== null && preg_match('/^[0-9a-f]{64}$/', (string) $row['checksum']) === 1);
}

$listFa = rawCall('GET', "$base/api/v1/files", null, $faToken);
$lf = jdec($listFa['body']);
check('FA file list -> 200 includes upload', $listFa['status'] === 200 && in_array($faFileId, array_column($lf['data']['items'] ?? [], 'id'), true));

$serve = rawCall('GET', "$base/api/v1/files/{$faFileId}", null, $faToken);
check('FA serve file -> 200 image/jpeg inline', $serve['status'] === 200 && str_contains((string) headerPair($serve['headers'], 'Content-Type'), 'image/jpeg'));
check('serve default is inline', str_contains((string) headerPair($serve['headers'], 'Content-Disposition'), 'inline'));

$down = rawCall('GET', "$base/api/v1/files/{$faFileId}?download=1", null, $faToken);
check('download=1 -> attachment disposition', str_contains((string) headerPair($down['headers'], 'Content-Disposition'), 'attachment'));

$fbServe = rawCall('GET', "$base/api/v1/files/{$faFileId}", null, $fbToken);
check('other farmer cannot read FA file -> 403', $fbServe['status'] === 403);

$phpUp = mpUpload("$base/api/v1/files/upload", $faToken, 'evil.php', '<?php echo "x";');
check('.php upload -> 400 INVALID_MIME', $phpUp['status'] === 400 && str_contains($phpUp['body'], 'INVALID_MIME'));

$big = str_repeat('A', 6 * 1024 * 1024);
$bigUp = mpUpload("$base/api/v1/files/upload", $faToken, 'big.jpg', $big);
check('oversized upload -> 400 FILE_TOO_LARGE', $bigUp['status'] === 400 && str_contains($bigUp['body'], 'FILE_TOO_LARGE'));

$upFb = mpUpload("$base/api/v1/files/upload", $fbToken, 'fb-own.jpg', $jpg, ['scope' => 'user']);
$ufb = jdec($upFb['body']);
$fbFileId = (int) ($ufb['data']['file']['id'] ?? 0);
check('FB upload own jpg -> 201', $upFb['status'] === 201);
$faCannotFb = rawCall('GET', "$base/api/v1/files/{$fbFileId}", null, $faToken);
check('FA cannot read FB file -> 403', $faCannotFb['status'] === 403);

echo "== Centre scope + folders ==";
$cmFolders = rawCall('GET', "$base/api/v1/files/folders", null, $cmToken);
check('CM lists folders -> 200', $cmFolders['status'] === 200);

$faFolders = rawCall('GET', "$base/api/v1/files/folders", null, $faToken);
check('FA cannot manage folders -> 403', $faFolders['status'] === 403);

$fldUp = rawCall('POST', "$base/api/v1/files/folders", '{"name":"P07 Centre Docs","scope":"centre"}', $cmToken);
$fld = jdec($fldUp['body']);
check('CM creates centre-scope folder -> 201', $fldUp['status'] === 201 && ($fld['data']['folder']['scope'] ?? '') === 'centre');

$cmFileUp = mpUpload("$base/api/v1/files/upload", $cmToken, 'centre-doc.jpg', $jpg, [
    'scope' => 'centre',
    'entity_category' => 'procurement_centre',
    'entity_id' => (string) $ctx['centre']['id'],
]);
$cmf = jdec($cmFileUp['body']);
$cmFileId = (int) ($cmf['data']['file']['id'] ?? 0);
check('CM centre-scope upload -> 201', $cmFileUp['status'] === 201 && $cmFileId > 0);

if (Database::testConnection() && $cmFileId > 0) {
    $crow = Database::selectOne("SELECT * FROM files WHERE id = ?", [$cmFileId]);
    check('centre file row has scope=centre', $crow !== null && $crow['scope'] === 'centre');
    $refs = Database::selectOne("SELECT COUNT(*) AS c FROM file_references WHERE file_id = ? AND source_type = 'procurement_centre' AND source_id = ?", [$cmFileId, $ctx['centre']['id']]);
    check('centre file has procurement_centre reference', ($refs['c'] ?? 0) >= 1);
}

$faCmf = rawCall('GET', "$base/api/v1/files/{$cmFileId}", null, $faToken);
check('farmer cannot access centre file -> 403', $faCmf['status'] === 403);
$cmSelfServe = rawCall('GET', "$base/api/v1/files/{$cmFileId}", null, $cmToken);
check('CM can access own-centre file -> 200', $cmSelfServe['status'] === 200);
$fbCmf = rawCall('GET', "$base/api/v1/files/{$cmFileId}", null, $fbToken);
check('other farmer cannot access centre file -> 403', $fbCmf['status'] === 403);

echo "== References + delete ==";
$refUp = mpUpload("$base/api/v1/files/upload", $faToken, 'doc-farmer.jpg', $jpg, [
    'scope' => 'user',
    'entity_category' => 'farmer',
    'entity_id' => (string) $faFarmerId,
]);
$rf = jdec($refUp['body']);
$refFileId = (int) ($rf['data']['file']['id'] ?? 0);
check('upload with farmer reference -> 201', $refUp['status'] === 201 && $refFileId > 0);

if (Database::testConnection() && $refFileId > 0) {
    $rc = Database::selectOne("SELECT COUNT(*) AS c FROM file_references WHERE file_id = ?", [$refFileId]);
    check('file_references row created', ($rc['c'] ?? 0) === 1);
}

$refDel = rawCall('DELETE', "$base/api/v1/files/{$refFileId}", null, $faToken);
check('referenced file delete -> 409 FILE_IN_USE', $refDel['status'] === 409 && str_contains($refDel['body'], 'FILE_IN_USE'));

$forceDel = rawCall('DELETE', "$base/api/v1/admin/files/{$refFileId}?force=1", null, $saToken);
check('SA force delete referenced file -> 200', $forceDel['status'] === 200 && (jdec($forceDel['body'])['data']['deleted'] ?? false) === true);
if (Database::testConnection()) {
    $gone = Database::selectOne("SELECT id FROM files WHERE id = ?", [$refFileId]);
    check('force delete hard-removes row + references', $gone === null);
}

$ownDel = rawCall('DELETE', "$base/api/v1/files/{$faFileId}", null, $faToken);
check('FA deletes own unreferenced file -> 200', $ownDel['status'] === 200);
if (Database::testConnection()) {
    $drow = Database::selectOne("SELECT * FROM files WHERE id = ?", [$faFileId]);
    check('soft delete -> status DELETED + deleted_at set', $drow !== null && $drow['status'] === 'DELETED' && $drow['deleted_at'] !== null);
    $phys = base_path('storage/app/private/files/' . basename((string) $drow['path_or_url']));
    check('soft delete removes physical file', !file_exists($phys));
}

$fbDelCmFile = rawCall('DELETE', "$base/api/v1/files/{$cmFileId}", null, $fbToken);
check('farmer cannot delete centre file -> 403', $fbDelCmFile['status'] === 403);

$afterDel = rawCall('GET', "$base/api/v1/files/{$faFileId}", null, $faToken);
check('deleted file no longer servable -> 404', $afterDel['status'] === 404);

echo "== Audit trail ==";
if (Database::testConnection()) {
    $audUp = Database::selectOne("SELECT id FROM audit_logs WHERE action = 'FILES_UPLOADED' AND module = 'FILES' ORDER BY id DESC LIMIT 1");
    $audDel = Database::selectOne("SELECT id FROM audit_logs WHERE action IN ('FILES_DELETED','FILES_FORCE_DELETED') ORDER BY id DESC LIMIT 1");
    check('audit logs recorded upload + delete', $audUp !== null && $audDel !== null);
} else {
    check('audit trail recorded (DB connected)', false);
}

echo "== RBAC: farmer vs admin file manager ==";
$adminList = rawCall('GET', "$base/api/v1/admin/files", null, $saToken);
check('SA admin file list -> 200', $adminList['status'] === 200);
$faAdminList = rawCall('GET', "$base/api/v1/admin/files", null, $faToken);
check('farmer cannot access admin file manager -> 403', $faAdminList['status'] === 403);

echo "== Cleanup guard (context) ==";
file_put_contents($ctxPath, json_encode(array_merge($ctx, [
    'run_cleanup' => [
        'fa_file' => (int) $faFileId,
        'fb_file' => (int) $fbFileId,
        'cm_file' => (int) $cmFileId,
        'center_id' => (int) $ctx['centre']['id'],
    ],
]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\nPASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);