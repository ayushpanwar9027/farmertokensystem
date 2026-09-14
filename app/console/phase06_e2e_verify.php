<?php

declare(strict_types=1);

// Phase 06 E2E verification via raw HTTP (captures 4xx/5xx bodies correctly).
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

$base = 'http://127.0.0.1:8090';
$pass = 0;
$fail = 0;

function check(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS: $label" . PHP_EOL; }
    else { $fail++; echo "  FAIL: $label" . PHP_EOL; }
}

function rawCall(string $method, string $url, ?string $body = null, ?string $token = null): array
{
    $headers = ['Content-Type: application/json'];
    if ($token !== null) {
        $headers[] = "Authorization: Bearer $token";
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'ignore_errors' => true,
        'content' => $body,
        'timeout' => 8,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0])) {
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
        $status = (int) ($m[1] ?? 0);
    }
    return ['status' => $status, 'body' => $raw];
}

echo "== Phase 06 E2E Verification ==\n";

$h = rawCall('GET', "$base/health");
check('GET /health -> 200', $h['status'] === 200);

$saLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"7123456781","password":"Sa06Test@1234"}');
$saData = json_decode($saLogin['body'], true);
$saToken = $saData['data']['access_token'] ?? null;
check('SA login -> 200 + token', $saLogin['status'] === 200 && is_string($saToken) && strlen($saToken) > 20);

$daLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"7123456782","password":"Da06Test@1234"}');
$daData = json_decode($daLogin['body'], true);
$daToken = $daData['data']['access_token'] ?? null;
check('DA login -> 200 + token', $daLogin['status'] === 200 && is_string($daToken) && strlen($daToken) > 20);

echo "== Settings (SA) ==\n";
$gs = rawCall('GET', "$base/api/v1/admin/settings", null, $saToken);
$gsData = json_decode($gs['body'], true);
check('GET settings -> 200', $gs['status'] === 200);
check('GET settings grouped', isset($gsData['data']['groups']) && count($gsData['data']['groups']) >= 6);

$ps = rawCall('PUT', "$base/api/v1/admin/settings", '{"settings":{"system_name":"Phase06 E2E System","session_timeout_minutes":"42"}}', $saToken);
$psData = json_decode($ps['body'], true);
check('PUT settings -> 200', $ps['status'] === 200);
check('PUT settings coerced INT 42', ($psData['data']['updated']['session_timeout_minutes'] ?? null) === 42);
check('PUT settings STRING applied', ($psData['data']['updated']['system_name'] ?? null) === 'Phase06 E2E System');

$bad = rawCall('PUT', "$base/api/v1/admin/settings", '{"settings":{"session_timeout_minutes":"abc"}}', $saToken);
check('PUT settings wrong INT type -> 400 VALIDATION_ERROR', $bad['status'] === 400 && str_contains($bad['body'], 'VALIDATION_ERROR'));

$unk = rawCall('PUT', "$base/api/v1/admin/settings", '{"settings":{"not_a_real_key":"x"}}', $saToken);
check('PUT settings unknown key -> 400', $unk['status'] === 400);

echo "== Secrets (SA) ==";
$sec1 = rawCall('GET', "$base/api/v1/admin/secrets", null, $saToken);
$sec1Data = json_decode($sec1['body'], true);
$jwt = null;
foreach ($sec1Data['data']['secrets'] ?? [] as $entry) {
    if ($entry['key'] === 'jwt_secret') { $jwt = $entry; }
}
check('GET secrets -> 200', $sec1['status'] === 200);
check('secrets list includes jwt_secret', $jwt !== null);
check('unset secret exposes exists=false + no last4', $jwt !== null && $jwt['exists'] === false && $jwt['last4'] === null);
check('no plaintext leak in initial list', !str_contains($sec1['body'], 'e2e-secret-value'));

$sp = rawCall('PUT', "$base/api/v1/admin/secrets", '{"key":"jwt_secret","value":"e2e-secret-value-1234"}', $saToken);
$spData = json_decode($sp['body'], true);
check('PUT secret -> 200', $sp['status'] === 200);
check('PUT secret returns masked last4', ($spData['data']['secret']['last4'] ?? null) === '1234');

$sec2 = rawCall('GET', "$base/api/v1/admin/secrets", null, $saToken);
check('secret now exists with last4 only', str_contains($sec2['body'], '"last4":"1234"'));
check('secrets list still masks full value', !str_contains($sec2['body'], 'e2e-secret-value'));

$bk = rawCall('PUT', "$base/api/v1/admin/secrets", '{"key":"not_a_secret","value":"x"}', $saToken);
check('PUT secret unknown key -> 400', $bk['status'] === 400);

echo "== Maintenance ==";
$mon = rawCall('PUT', "$base/api/v1/admin/maintenance", '{"enabled":true,"message":"E2E maintenance in progress","expected_available_at":"2026-09-08T18:00:00Z"}', $saToken);
$monData = json_decode($mon['body'], true);
check('PUT maintenance on -> 200', $mon['status'] === 200);
check('maintenance enabled', ($monData['data']['maintenance']['enabled'] ?? null) === true);

$blocked = rawCall('GET', "$base/api/v1/auth/me");
check('unauth request while maintenance -> 503', $blocked['status'] === 503);
check('503 code = MAINTENANCE_MODE', str_contains($blocked['body'], 'MAINTENANCE_MODE'));
check('503 surfaces message', str_contains($blocked['body'], 'E2E maintenance in progress'));
check('503 passes expected_available_at', str_contains($blocked['body'], '2026-09-08T18:00:00Z'));

$saBypass = rawCall('GET', "$base/api/v1/auth/me", null, $saToken);
check('SA bypasses maintenance -> 200', $saBypass['status'] === 200);

$hm = rawCall('GET', "$base/health/maintenance");
check('/health/maintenance reachable during outage -> 200', $hm['status'] === 200);

echo "== RBAC gates on new endpoints ==";
$off1 = rawCall('PUT', "$base/api/v1/admin/maintenance", '{"enabled":false}', $saToken);
check('maintenance disabled for RBAC section', $off1['status'] === 200);

$daRead = rawCall('GET', "$base/api/v1/admin/settings", null, $daToken);
check('DA can view settings -> 200 (settings.view)', $daRead['status'] === 200);
$daWrite = rawCall('PUT', "$base/api/v1/admin/settings", '{"settings":{"system_name":"hax"}}', $daToken);
check('DA cannot modify settings -> 403 (no settings.manage)', $daWrite['status'] === 403);
$daSec = rawCall('GET', "$base/api/v1/admin/secrets", null, $daToken);
check('DA cannot view secrets -> 403 (no secret_manage)', $daSec['status'] === 403);
$daMaint = rawCall('PUT', "$base/api/v1/admin/maintenance", '{"enabled":false}', $daToken);
check('DA cannot toggle maintenance -> 403 (no maintenance.manage)', $daMaint['status'] === 403);
$anon = rawCall('GET', "$base/api/v1/admin/settings");
check('anonymous blocked -> 401/403', $anon['status'] === 401 || $anon['status'] === 403);

echo "== Maintenance off + restore ==";
$moff = rawCall('PUT', "$base/api/v1/admin/maintenance", '{"enabled":false}', $saToken);
$moffData = json_decode($moff['body'], true);
check('PUT maintenance off -> 200', $moff['status'] === 200);
check('maintenance disabled', ($moffData['data']['maintenance']['enabled'] ?? null) === false);
$restore = rawCall('PUT', "$base/api/v1/admin/settings", '{"settings":{"system_name":"Farmer Procurement System","session_timeout_minutes":30}}', $saToken);
check('settings restored -> 200', $restore['status'] === 200);

echo "\nPASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);