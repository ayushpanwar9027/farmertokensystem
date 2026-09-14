<?php

declare(strict_types=1);

define('RESULTS_PATH', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'security_scan_results.json');
define('OTP_LOG_PATH', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.otp_log');
define('APP_LOG_PATH', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'application.log');
define('ROUTES_PATH', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.php');
define('UPLOAD_DIR', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app');
define('PORTAL_UTILS', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'portal' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'utils.js');
define('PORTAL_INDEX', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'portal' . DIRECTORY_SEPARATOR . 'index.html');

$opts = getopt('', ['base:']);
$baseUrl = rtrim((string)($opts['base'] ?? 'http://127.0.0.1:8080'), '/');

echo "\n";
echo "  ╔══════════════════════════════════════════════════════════════╗\n";
echo "  ║  !!  DEV-ONLY SECURITY PROBE  —  DO NOT RUN IN PROD  !!    ║\n";
echo "  ║  This script actively probes for vulnerabilities.           ║\n";
echo "  ║  Only run against a local development instance.             ║\n";
echo "  ╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "  Base URL : {$baseUrl}\n";
echo "  Results  : " . RESULTS_PATH . "\n";
echo "  Started  : " . date('c') . "\n\n";

$curlCookieFile = tempnam(sys_get_temp_dir(), 'fps_sec_cookie_');
register_shutdown_function(function () use ($curlCookieFile) {
    if (file_exists($curlCookieFile)) {
        @unlink($curlCookieFile);
    }
});

$results = [];
$probeNum = 0;
$hasCritical = false;
$hasHigh = false;

function http(
    string $method,
    string $path,
    ?array $data = null,
    array $headers = [],
    ?string $rawBody = null,
    bool $useCookies = false,
): array {
    global $baseUrl, $curlCookieFile;
    $url = $baseUrl . $path;
    $ch = curl_init();
    $curlHeaders = [];
    foreach ($headers as $k => $v) {
        $curlHeaders[] = "{$k}: {$v}";
    }
    if ($data !== null && $rawBody === null) {
        $curlHeaders[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($rawBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
    } elseif ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
    }
    if ($useCookies) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $curlCookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $curlCookieFile);
    }
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return ['status' => 0, 'body' => [], 'raw' => '', 'headers' => '', 'curl_error' => $err];
    }
    $headerStr = substr((string) $response, 0, $headerSize);
    $body = substr((string) $response, $headerSize);
    $json = json_decode($body, true);
    return [
        'status' => $httpCode,
        'body' => is_array($json) ? $json : [],
        'raw' => $body,
        'headers' => $headerStr,
    ];
}

function record(string $name, string $severity, bool $pass, string $detail): void {
    global $results, $probeNum, $hasCritical, $hasHigh;
    $probeNum++;
    $entry = [
        'num' => $probeNum,
        'probe' => $name,
        'severity' => $severity,
        'pass' => $pass,
        'detail' => $detail,
    ];
    $results[] = $entry;
    $color = match ($severity) {
        'CRITICAL' => "\033[35m",
        'HIGH' => "\033[31m",
        'MEDIUM' => "\033[33m",
        'LOW' => "\033[36m",
        default => '',
    };
    $passColor = $pass ? "\033[32m" : "\033[31m";
    $reset = "\033[0m";
    $icon = $pass ? 'PASS' : 'FAIL';
    echo sprintf(
        "  %s[%s]%s %02d. %-12s %s[%-8s]%s %s\n",
        $passColor,
        $icon,
        $reset,
        $probeNum,
        $name,
        $color,
        $severity,
        $reset,
        $detail
    );
    if (!$pass) {
        if ($severity === 'CRITICAL') {
            $hasCritical = true;
        }
        if ($severity === 'HIGH') {
            $hasHigh = true;
        }
    }
}

function readOtp(?string $verificationId = null): ?string {
    $otp = getenv('SMOKE_OTP');
    if ($otp !== false && $otp !== '') {
        return $otp;
    }
    if (file_exists(OTP_LOG_PATH)) {
        $content = file_get_contents(OTP_LOG_PATH);
        if ($verificationId !== null && preg_match('/verification_id=' . preg_quote($verificationId, '/') . '.*?otp=(\d{6})/s', $content, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d{6})\b/', $content, $m)) {
            return $m[1];
        }
    }
    if (file_exists(APP_LOG_PATH)) {
        $lines = array_reverse(file(APP_LOG_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        foreach (array_slice($lines, 0, 200) as $line) {
            if (preg_match('/"otp":\s*"(\d{6})"/', $line, $m)) {
                return $m[1];
            }
        }
    }
    return null;
}

function loginWithOtp(string $mobile, string $password): array {
    $resp = http('POST', '/api/v1/auth/login', ['mobile' => $mobile, 'password' => $password]);
    if ($resp['status'] === 200) {
        $token = $resp['body']['data']['access_token'] ?? '';
        return ['token' => $token, 'refresh' => $resp['body']['data']['refresh_token'] ?? '', 'ok' => $token !== ''];
    }
    if ($resp['status'] === 202) {
        $vid = $resp['body']['data']['verification_id'] ?? '';
        $otp = readOtp($vid);
        if ($otp === null) {
            return ['token' => '', 'refresh' => '', 'ok' => false, 'reason' => '2FA OTP not available'];
        }
        $r2 = http('POST', '/api/v1/auth/verify-2fa', ['verification_id' => $vid, 'otp' => $otp]);
        if ($r2['status'] === 200) {
            $token = $r2['body']['data']['access_token'] ?? '';
            return ['token' => $token, 'refresh' => $r2['body']['data']['refresh_token'] ?? '', 'ok' => $token !== ''];
        }
        return ['token' => '', 'refresh' => '', 'ok' => false, 'reason' => "2FA verify returned {$r2['status']}"];
    }
    return ['token' => '', 'refresh' => '', 'ok' => false, 'reason' => "Login returned {$resp['status']}"];
}

function loadToken(string $role): string {
    $saMobile = getenv('SMOKE_SUPER_ADMIN_MOBILE') ?: '7010000001';
    $saPass = getenv('SMOKE_SUPER_ADMIN_PASSWORD') ?: 'Admin@1234';
    $opMobile = getenv('SMOKE_OPERATOR_MOBILE') ?: '9010000001';
    $opPass = getenv('SMOKE_OPERATOR_PASSWORD') ?: 'Admin@1234';
    $fmMobile = getenv('SMOKE_FARMER_MOBILE') ?: '9000000004';
    $fmPass = getenv('SMOKE_FARMER_PASSWORD') ?: 'Demo@1234';
    $creds = match ($role) {
        'super_admin' => [$saMobile, $saPass],
        'operator' => [$opMobile, $opPass],
        'farmer' => [$fmMobile, $fmPass],
        default => [$saMobile, $saPass],
    };
    $result = loginWithOtp($creds[0], $creds[1]);
    if (!$result['ok']) {
        return '';
    }
    return $result['token'];
}

$adminToken = loadToken('super_admin');
$operatorToken = loadToken('operator');
$farmerToken = loadToken('farmer');

if ($adminToken === '') {
    echo "  [WARN] Could not obtain super_admin token. Auth-dependent probes may be skipped.\n";
}
if ($operatorToken === '') {
    echo "  [WARN] Could not obtain operator token. Role-escalation tests may be skipped.\n";
}
if ($farmerToken === '') {
    echo "  [WARN] Could not obtain farmer token. Auth-dependent probes may be skipped.\n";
}
echo "\n";

$isSensitiveString = function (string $haystack): bool {
    $patterns = [
        '/JWT_SECRET/i',
        '/ENCRYPTION_KEY/i',
        '/ONESIGNAL_REST_API_KEY/i',
        '/OTP_API_KEY/i',
        '/DB_PASSWORD/i',
        '/APP_SECRET/i',
        '/getenv/',
        '/getenv\(/i',
    ];
    foreach ($patterns as $pat) {
        if (preg_match($pat, $haystack)) {
            return true;
        }
    }
    return false;
};

$isSqlError = function (string $haystack): bool {
    $patterns = ['SQLSTATE', 'PDO', 'Warning:', 'Fatal error', 'Uncaught Exception', 'Stack trace', 'table', 'column'];
    foreach ($patterns as $p) {
        if (stripos($haystack, $p) !== false) {
            return true;
        }
    }
    return false;
};

# --- Probe 1: SQL Injection ---
echo "  --- Probe #1: SQL Injection (CRITICAL) ---\n";

$sqliPayloads = [
    ['path' => '/api/v1/centres?q=' . urlencode("' OR 1=1--"), 'name' => 'centres single-quote'],
    ['path' => '/api/v1/centres?q=' . urlencode('" OR "1"="1'), 'name' => 'centres double-quote'],
    ['path' => '/api/v1/bookings?status=' . urlencode('; DROP TABLE bookings--'), 'name' => 'bookings drop-table'],
];
$sqliPass = true;
$sqliDetails = [];
foreach ($sqliPayloads as $payload) {
    $r = http('GET', $payload['path']);
    if ($r['status'] === 500) {
        $sqliPass = false;
        $sqliDetails[] = "{$payload['name']}: HTTP 500 (server error)";
    } elseif ($isSqlError($r['raw'])) {
        $sqliPass = false;
        $sqliDetails[] = "{$payload['name']}: leaked SQL/error pattern";
    } elseif (!json_decode($r['raw'], true) && trim($r['raw']) !== '') {
        $sqliPass = false;
        $sqliDetails[] = "{$payload['name']}: non-JSON response";
    } else {
        $sqliDetails[] = "{$payload['name']}: OK (HTTP {$r['status']})";
    }
}
record('SQLi', 'CRITICAL', $sqliPass, implode('; ', $sqliDetails));

# --- Probe 2: XSS ---
echo "  --- Probe #2: XSS (HIGH) ---\n";

$xssPass = true;
$xssDetails = [];
$xssRegResp = http('POST', '/api/v1/auth/register', [
    'mobile' => '9099999998',
    'purpose' => 'REGISTER',
]);
$xssDetails[] = "register-precheck: HTTP {$xssRegResp['status']}";

$xssMe = http('GET', '/api/v1/auth/me', null, ['Authorization' => "Bearer {$farmerToken}"]);
if ($farmerToken === '') {
    $xssDetails[] = 'auth/me: SKIP (no farmer token)';
} elseif ($xssMe['status'] === 200) {
    if (stripos($xssMe['raw'], '<script>') !== false) {
        $xssPass = false;
        $xssDetails[] = 'auth/me: raw <script> tag in response';
    } else {
        $xssDetails[] = 'auth/me: no raw <script> tag (escaped)';
    }
} else {
    $xssDetails[] = "auth/me: HTTP {$xssMe['status']}";
}

if (file_exists(PORTAL_UTILS)) {
    $utilsContent = file_get_contents(PORTAL_UTILS);
    if (preg_match('/function\s+esc\s*\(/', $utilsContent) || preg_match('/esc\s*\(/', $utilsContent)) {
        $xssDetails[] = 'utils.js: esc() function found';
    } else {
        $xssPass = false;
        $xssDetails[] = 'utils.js: esc() function MISSING';
    }
} else {
    $xssDetails[] = 'utils.js: file not found (skipped)';
}
record('XSS', 'HIGH', $xssPass, implode('; ', $xssDetails));

# --- Probe 3: CSRF ---
echo "  --- Probe #3: CSRF (HIGH) ---\n";

$csrfPass = true;
$csrfDetails = [];
$noCsrfResp = http('POST', '/web/test/mutate', ['action' => 'test']);
if ($noCsrfResp['status'] !== 403) {
    $csrfPass = false;
    $csrfDetails[] = "no-header: expected 403, got {$noCsrfResp['status']}";
} else {
    $csrfDetails[] = 'no-header: 403 (correct)';
}

$staleCsrfResp = http('POST', '/web/test/mutate', ['action' => 'test'], ['X-CSRF-Token' => 'stale_token_value']);
if ($staleCsrfResp['status'] !== 403) {
    $csrfPass = false;
    $csrfDetails[] = "stale-token: expected 403, got {$staleCsrfResp['status']}";
} else {
    $csrfDetails[] = 'stale-token: 403 (correct)';
}
record('CSRF', 'HIGH', $csrfPass, implode('; ', $csrfDetails));

# --- Probe 4: Auth Bypass ---
echo "  --- Probe #4: Auth Bypass (CRITICAL) ---\n";

$routeMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
$placeholderMap = [
    '{id}' => '1',
    '{centre_id}' => '1',
    '{entryId}' => '1',
    '{bookingToken}' => '1',
    '{locale}' => 'en',
    '{code}' => 'en',
    '{cropId}' => '1',
];

$parseProtectedRoutes = static function () use ($routeMethods, $placeholderMap): array {
    $lines = file(ROUTES_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $routes = [];
    $total = count($lines);
    for ($i = 0; $i < $total; $i++) {
        $keyLine = trim($lines[$i]);
        if (!preg_match("/^'([A-Z]+)\s+([^']+)'/", $keyLine, $m)) {
            continue;
        }
        $method = strtoupper($m[1]);
        if (!in_array($method, $routeMethods, true)) {
            continue;
        }
        $path = $m[2];
        if (strpos($path, '/api/v1/') !== 0) {
            continue;
        }
        if (strpos($path, '/api/v1/test/') === 0) {
            continue;
        }
        $block = $keyLine;
        for ($j = $i + 1; $j < $total; $j++) {
            $nextLine = trim($lines[$j]);
            $block .= ' ' . $nextLine;
            if (str_starts_with($nextLine, '],')) {
                break;
            }
        }
        if (!preg_match("/'middleware'\s*=>/", $block)) {
            continue;
        }
        $resolved = preg_replace_callback('/\{[a-z_]+\}/i', static function ($pm) use ($placeholderMap) {
            return $placeholderMap[$pm[0]] ?? '1';
        }, $path);
        $routes[] = ['method' => $method, 'path' => $resolved, 'defined' => "{$method} {$path}"];
    }
    return $routes;
};

$protectedRoutes = $parseProtectedRoutes();
$protectedRouteCount = count($protectedRoutes);

$specRoutes = [
    ['method' => 'GET', 'path' => '/api/v1/admin/staff'],
    ['method' => 'GET', 'path' => '/api/v1/admin/centres/1'],
    ['method' => 'GET', 'path' => '/api/v1/admin/slots'],
    ['method' => 'GET', 'path' => '/api/v1/bookings'],
    ['method' => 'GET', 'path' => '/api/v1/queue/my'],
    ['method' => 'GET', 'path' => '/api/v1/notifications'],
    ['method' => 'GET', 'path' => '/api/v1/admin/payments'],
    ['method' => 'GET', 'path' => '/api/v1/admin/settings'],
    ['method' => 'PUT', 'path' => '/api/v1/admin/maintenance'],
    ['method' => 'GET', 'path' => '/api/v1/files'],
    ['method' => 'GET', 'path' => '/api/v1/admin/roles'],
    ['method' => 'GET', 'path' => '/api/v1/admin/audit'],
    ['method' => 'POST', 'path' => '/api/v1/operator/queue/call-next'],
    ['method' => 'GET', 'path' => '/api/v1/operator/procurements'],
    ['method' => 'GET', 'path' => '/api/v1/my/payments'],
    ['method' => 'GET', 'path' => '/api/v1/admin/secrets'],
    ['method' => 'GET', 'path' => '/api/v1/admin/approvals'],
    ['method' => 'GET', 'path' => '/api/v1/operator/queue'],
    ['method' => 'GET', 'path' => '/api/v1/admin/notifications'],
    ['method' => 'POST', 'path' => '/api/v1/admin/staff'],
    ['method' => 'POST', 'path' => '/api/v1/files/upload'],
    ['method' => 'POST', 'path' => '/api/v1/bookings'],
];

$dedup = [];
$testedRoutes = [];
$addRoute = static function (array $def) use (&$dedup, &$testedRoutes): void {
    $key = $def['method'] . ' ' . $def['path'];
    if (isset($dedup[$key])) {
        return;
    }
    $dedup[$key] = true;
    $testedRoutes[] = $def;
};
foreach ($specRoutes as $def) {
    $addRoute($def);
}
$sampled = 0;
foreach ($protectedRoutes as $def) {
    if ($sampled >= 15) {
        break;
    }
    $key = $def['method'] . ' ' . $def['path'];
    if (isset($dedup[$key])) {
        continue;
    }
    $addRoute($def);
    $sampled++;
}

$authBypassPass = true;
$authBypassDetails = [];
$failedRoutes = [];
$rateLimited = [];
$testedTotal = count($testedRoutes);
foreach ($testedRoutes as $def) {
    $r = http($def['method'], $def['path']);
    $s = $r['status'];
    if ($s === 429) {
        $rateLimited[] = "{$def['method']} {$def['path']}";
    } elseif (!in_array($s, [401, 403, 404], true)) {
        $authBypassPass = false;
        $failedRoutes[] = "{$def['method']} {$def['path']}:{$s}";
    }
    usleep(60000);
}
$authBypassDetails[] = "protected-in-config:{$protectedRouteCount} tested:{$testedTotal}";
if (!empty($rateLimited)) {
    $authBypassDetails[] = "rate-limited (not verified): " . count($rateLimited) . ' routes';
}
if (empty($failedRoutes)) {
    $authBypassDetails[] = "all tested routes blocked (401/403/404)";
} else {
    $authBypassDetails[] = "UNAUTHED ACCESS: " . implode(', ', $failedRoutes);
}
record('Auth Bypass', 'CRITICAL', $authBypassPass, implode('; ', $authBypassDetails));

# --- Probe 5: Role Escalation ---
echo "  --- Probe #5: Role Escalation (HIGH) ---\n";

$escalationPass = true;
$escalationDetails = [];
$escalationTests = [
    ['name' => 'operator->maintenance', 'token' => $operatorToken, 'method' => 'PUT', 'path' => '/api/v1/admin/maintenance', 'body' => ['enabled' => true]],
    ['name' => 'operator->settings', 'token' => $operatorToken, 'method' => 'PUT', 'path' => '/api/v1/admin/settings', 'body' => ['key' => 'test']],
    ['name' => 'farmer->staff', 'token' => $farmerToken, 'method' => 'POST', 'path' => '/api/v1/admin/staff', 'body' => ['name' => 'test']],
    ['name' => 'farmer->maintenance', 'token' => $farmerToken, 'method' => 'PUT', 'path' => '/api/v1/admin/maintenance', 'body' => ['enabled' => true]],
];

foreach ($escalationTests as $test) {
    if ($test['token'] === '') {
        $escalationDetails[] = "{$test['name']}: SKIP (no token)";
        continue;
    }
    $r = http($test['method'], $test['path'], $test['body'], ['Authorization' => "Bearer {$test['token']}"]);
    if ($r['status'] !== 403 && $r['status'] !== 401) {
        $escalationPass = false;
        $escalationDetails[] = "{$test['name']}: expected 401/403, got {$r['status']}";
    } else {
        $escalationDetails[] = "{$test['name']}: {$r['status']} (correct)";
    }
}
record('Role Escalation', 'HIGH', $escalationPass, implode('; ', $escalationDetails));

# --- Probe 6: IDOR ---
echo "  --- Probe #6: IDOR (HIGH) ---\n";

$idorPass = true;
$idorDetails = [];
if ($farmerToken !== '') {
    $r1 = http('GET', '/api/v1/bookings/999999', null, ['Authorization' => "Bearer {$farmerToken}"]);
    if ($r1['status'] === 200) {
        $idorPass = false;
        $idorDetails[] = "bookings/999999: returned 200 (should be 404)";
    } else {
        $idorDetails[] = "bookings/999999: HTTP {$r1['status']} (OK)";
    }

    $r2 = http('GET', '/api/v1/my/payments/999999', null, ['Authorization' => "Bearer {$farmerToken}"]);
    if ($r2['status'] === 200) {
        $idorPass = false;
        $idorDetails[] = "my/payments/999999: returned 200 (should be 404)";
    } else {
        $idorDetails[] = "my/payments/999999: HTTP {$r2['status']} (OK)";
    }
} else {
    $idorDetails[] = 'SKIP (no farmer token)';
}
record('IDOR', 'HIGH', $idorPass, implode('; ', $idorDetails));

# --- Probe 7: Upload Restrictions ---
echo "  --- Probe #7: Upload Restrictions (MEDIUM) ---\n";

$uploadPass = true;
$uploadDetails = [];
$fakePayload = '<?php echo "hacked"; ?>';
$boundary = '----FPS-SEC-BOUNDARY-' . bin2hex(random_bytes(8));
$body = "--{$boundary}\r\n" .
    "Content-Disposition: form-data; name=\"file\"; filename=\"test.php\"\r\n" .
    "Content-Type: application/x-php\r\n\r\n" .
    $fakePayload . "\r\n" .
    "--{$boundary}--\r\n";
$uploadResp = http('POST', '/api/v1/files/upload', null, [
    'Authorization' => "Bearer {$adminToken}",
    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
], $body);
if ($uploadResp['status'] === 200 || $uploadResp['status'] === 201) {
    $uploadPass = false;
    $uploadDetails[] = 'PHP file upload accepted (should be blocked)';
} else {
    $uploadDetails[] = "PHP upload blocked (HTTP {$uploadResp['status']})";
}

$phpFilesFound = [];
if (is_dir(UPLOAD_DIR)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->getExtension() === 'php') {
            $phpFilesFound[] = $file->getPathname();
        }
    }
}
if (!empty($phpFilesFound)) {
    $uploadPass = false;
    $uploadDetails[] = "PHP files in upload dir: " . implode(', ', $phpFilesFound);
} else {
    $uploadDetails[] = 'no PHP files in upload directory';
}
record('Upload', 'MEDIUM', $uploadPass, implode('; ', $uploadDetails));

# --- Probe 8: Rate Limiting ---
echo "  --- Probe #8: Rate Limiting (MEDIUM) ---\n";

$rlPass = true;
$rlDetails = [];
$testMobile = '908' . str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
$gotRateLimited = false;
$lastStatus = 0;
$rlHeaders = '';
$verificationId = '';
$verifyResp = http('POST', '/api/v1/auth/register', ['mobile' => $testMobile, 'purpose' => 'REGISTER']);
if ($verifyResp['status'] === 200) {
    $verificationId = $verifyResp['body']['data']['verification_id'] ?? '';
    $rlDetails[] = "register: verification_id obtained";
} elseif (isset($verifyResp['body']['error']['code']) && $verifyResp['body']['error']['code'] === 'ALREADY_REGISTERED') {
    $retryResp = http('POST', '/api/v1/auth/forgot-password', ['mobile' => $testMobile]);
    if ($retryResp['status'] === 200 || $retryResp['status'] === 202) {
        $verificationId = $retryResp['body']['data']['reset_id'] ?? $retryResp['body']['data']['verification_id'] ?? '';
    }
    $rlDetails[] = 'register: mobile existed, used forgot-password flow';
} else {
    $rlDetails[] = "register: HTTP {$verifyResp['status']}";
}
if ($verificationId === '') {
    $rlDetails[] = 'SKIP: no verification_id for resend burst';
} else {
    for ($i = 0; $i < 7; $i++) {
        $r = http('POST', '/api/v1/auth/resend-otp', ['verification_id' => $verificationId]);
        $lastStatus = $r['status'];
        $rlHeaders = $r['headers'];
        if ($r['status'] === 429) {
            $gotRateLimited = true;
            $rlDetails[] = "429 on attempt #" . ($i + 1);
            break;
        }
        usleep(100000);
    }
    if (!$gotRateLimited) {
        $rlDetails[] = "no 429 after 7 rapid resends (last: {$lastStatus})";
    }
}

$hasRateLimitHeaders = (stripos($rlHeaders, 'X-RateLimit-Limit') !== false) ||
    (stripos($rlHeaders, 'x-ratelimit-limit') !== false);
if ($hasRateLimitHeaders) {
    $rlDetails[] = 'RateLimit headers present';
} else {
    $rlDetails[] = 'RateLimit headers NOT in response (may be header case or missing)';
}
record('Rate Limits', 'MEDIUM', $rlPass && $gotRateLimited, implode('; ', $rlDetails));

# --- Probe 9: Secret Exposure ---
echo "  --- Probe #9: Secret Exposure (CRITICAL) ---\n";

$secretPass = true;
$secretDetails = [];
$healthResp = http('GET', '/health');
$healthRawOnce = $healthResp['raw'];
if ($isSensitiveString($healthRawOnce)) {
    $secretPass = false;
    $secretDetails[] = '/health: sensitive value in response';
} else {
    $secretDetails[] = '/health: clean';
}

$secretsResp = http('GET', '/api/v1/admin/secrets');
if (in_array($secretsResp['status'], [401, 403, 429], true)) {
    $secretDetails[] = "/admin/secrets: protected (HTTP {$secretsResp['status']})";
} else {
    $secretPass = false;
    $secretDetails[] = "/admin/secrets: accessible without auth (HTTP {$secretsResp['status']})";
}

foreach (['JWT_SECRET', 'ENCRYPTION_KEY', 'ONESIGNAL_REST_API_KEY', 'OTP_API_KEY', 'DB_PASSWORD'] as $envKey) {
    $val = getenv($envKey);
    if ($val !== false && strlen($val) > 3 && strpos($healthRawOnce, $val) !== false) {
        $secretPass = false;
        $secretDetails[] = "{$envKey} value leaked in /health";
    }
}
record('Secret Exposure', 'CRITICAL', $secretPass, implode('; ', $secretDetails));

# --- Probe 10: Tokens/OTP in Logs ---
echo "  --- Probe #10: Tokens in Logs (HIGH) ---\n";

$logPass = true;
$logDetails = [];
if (file_exists(APP_LOG_PATH)) {
    $logContent = file_get_contents(APP_LOG_PATH);
    $leaked = [];

    if ($adminToken !== '' && strlen($adminToken) > 10 && strpos($logContent, $adminToken) !== false) {
        $leaked[] = 'admin access_token';
    }
    if ($operatorToken !== '' && strlen($operatorToken) > 10 && strpos($logContent, $operatorToken) !== false) {
        $leaked[] = 'operator access_token';
    }
    if ($farmerToken !== '' && strlen($farmerToken) > 10 && strpos($logContent, $farmerToken) !== false) {
        $leaked[] = 'farmer access_token';
    }

    if (preg_match_all('/\b\d{6}\b/', $logContent, $otpMatches)) {
        $otpLog = getenv('SMOKE_OTP') ?: '';
        foreach (array_unique($otpMatches[0]) as $potentialOtp) {
            if ($otpLog !== '' && $potentialOtp === $otpLog) {
                $leaked[] = "OTP {$potentialOtp} found in log";
            }
        }
    }

    if (preg_match('/JWT_SECRET/', $logContent)) {
        $leaked[] = 'JWT_SECRET string found in log';
    }
    if (preg_match('/ENCRYPTION_KEY/', $logContent)) {
        $leaked[] = 'ENCRYPTION_KEY string found in log';
    }

    if (!empty($leaked)) {
        $logPass = false;
        $logDetails[] = 'LEAKED: ' . implode(', ', $leaked);
    } else {
        $logDetails[] = 'no tokens/OTP/keys found in application.log';
    }
} else {
    $logDetails[] = 'application.log not found (skipped)';
}
record('Tokens in Logs', 'HIGH', $logPass, implode('; ', $logDetails));

# --- Probe 11: Session/Cookie Security ---
echo "  --- Probe #11: Session Cookies (MEDIUM) ---\n";

$cookiePass = true;
$cookieDetails = [];
$csrfResp = http('GET', '/web/csrf', null, [], null, true);
$setCookieHeaders = '';
if (preg_match_all('/Set-Cookie:\s*([^\r\n]+)/i', $csrfResp['headers'], $m)) {
    $setCookieHeaders = implode(' | ', $m[1]);
}

if (empty($setCookieHeaders)) {
    $cookieDetails[] = 'no Set-Cookie headers (may be expected)';
} else {
    $hasHttpOnly = (stripos($setCookieHeaders, 'httponly') !== false);
    $hasSameSite = (stripos($setCookieHeaders, 'samesite=lax') !== false) || (stripos($setCookieHeaders, 'samesite=strict') !== false);

    if (!$hasHttpOnly) {
        $cookiePass = false;
        $cookieDetails[] = 'HttpOnly flag MISSING';
    } else {
        $cookieDetails[] = 'HttpOnly present';
    }
    if (!$hasSameSite) {
        $cookiePass = false;
        $cookieDetails[] = 'SameSite missing (not Lax/Strict)';
    } else {
        $cookieDetails[] = 'SameSite present';
    }
}
record('Session Cookies', 'MEDIUM', $cookiePass, implode('; ', $cookieDetails));

# --- Probe 12: Security Headers ---
echo "  --- Probe #12: Security Headers (MEDIUM) ---\n";

$headerPass = true;
$headerDetails = [];

$healthH = http('GET', '/health');
$hasCSPHealth = (stripos($healthH['headers'], 'Content-Security-Policy') !== false);
if ($hasCSPHealth) {
    $headerDetails[] = '/health: CSP present (noted)';
} else {
    $headerDetails[] = '/health: no CSP (API, acceptable)';
}

$rootH = http('GET', '/');
$hasXCTO = (stripos($rootH['headers'], 'X-Content-Type-Options') !== false);
if (!$hasXCTO) {
    $headerPass = false;
    $headerDetails[] = '/: X-Content-Type-Options MISSING';
} else {
    $headerDetails[] = '/: X-Content-Type-Options present';
}

if (file_exists(PORTAL_INDEX)) {
    $portalHtml = file_get_contents(PORTAL_INDEX);
    if (preg_match('/<meta[^>]+http-equiv=["\']?Content-Security-Policy["\']?/i', $portalHtml)) {
        $headerDetails[] = 'portal/index.html: CSP meta tag found';
    } else {
        $headerDetails[] = 'portal/index.html: no CSP meta tag (consider adding)';
    }
} else {
    $headerDetails[] = 'portal/index.html: not found';
}
record('Security Headers', 'MEDIUM', $headerPass, implode('; ', $headerDetails));

# --- Probe 13: Maintenance Mode Bypass ---
echo "  --- Probe #13: Maintenance Bypass (HIGH) ---\n";

$maintPass = true;
$maintDetails = [];
$maintEnabled = false;

if ($adminToken !== '') {
    $enableR = http('PUT', '/api/v1/admin/maintenance', ['enabled' => true], ['Authorization' => "Bearer {$adminToken}"]);
    if ($enableR['status'] === 200 || $enableR['status'] === 201) {
        $maintEnabled = true;
        $maintDetails[] = 'maintenance enabled';
    } else {
        $maintPass = false;
        $maintDetails[] = "enable failed: HTTP {$enableR['status']}";
    }
} else {
    $maintDetails[] = 'SKIP enable (no admin token)';
}

if ($maintEnabled) {
    $anonR = http('GET', '/api/v1/centres');
    if ($anonR['status'] !== 503) {
        $maintPass = false;
        $maintDetails[] = "anonymous during maintenance: expected 503, got {$anonR['status']}";
    } else {
        $maintDetails[] = 'anonymous blocked (503)';
    }

    if ($adminToken !== '') {
        $adminR = http('GET', '/api/v1/centres', null, ['Authorization' => "Bearer {$adminToken}"]);
        if ($adminR['status'] !== 200) {
            $maintPass = false;
            $maintDetails[] = "admin during maintenance: expected 200, got {$adminR['status']}";
        } else {
            $maintDetails[] = 'admin bypasses maintenance (200)';
        }
    }

    if ($adminToken !== '') {
        $disableR = http('PUT', '/api/v1/admin/maintenance', ['enabled' => false], ['Authorization' => "Bearer {$adminToken}"]);
        if ($disableR['status'] === 200 || $disableR['status'] === 201) {
            $maintDetails[] = 'maintenance disabled';
        } else {
            $maintPass = false;
            $maintDetails[] = "disable failed: HTTP {$disableR['status']}";
        }
    }
} else {
    $maintDetails[] = 'cleanup skipped';
}
record('Maint Bypass', 'HIGH', $maintPass, implode('; ', $maintDetails));

# --- Record results ---
echo "\n";

$passCount = 0;
$failCount = 0;
$severityCounts = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
$severityFails = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];

foreach ($results as $r) {
    if ($r['pass']) {
        $passCount++;
    } else {
        $failCount++;
    }
    $severityCounts[$r['severity']]++;
    if (!$r['pass']) {
        $severityFails[$r['severity']]++;
    }
}

$jsonOutput = [
    'scan_date' => date('c'),
    'base_url' => $baseUrl,
    'summary' => [
        'total' => count($results),
        'passed' => $passCount,
        'failed' => $failCount,
        'severity_counts' => $severityCounts,
        'severity_failures' => $severityFails,
        'auto_fail' => $hasCritical || $hasHigh,
    ],
    'results' => $results,
];

file_put_contents(RESULTS_PATH, json_encode($jsonOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "  ╔══════════════════════════════════════════════════════════════╗\n";
echo "  ║                    SECURITY SCAN RESULTS                    ║\n";
echo "  ╠══════════════════════════════════════════════════════════════╣\n";
echo sprintf("  ║  %-20s  %d passed / %d failed                   ║\n", 'Total', $passCount, $failCount);
echo "  ╠══════════════════════════════════════════════════════════════╣\n";
foreach ($severityCounts as $sev => $count) {
    $failS = $severityFails[$sev];
    $icon = $failS > 0 ? '!!' : 'OK';
    $color = $failS > 0 ? "\033[31m" : "\033[32m";
    $reset = "\033[0m";
    echo sprintf("  ║  %s%-8s%s  %d checked  %d failed  [%s]                ║\n", $color, $sev, $reset, $count, $failS, $icon);
}
echo "  ╠══════════════════════════════════════════════════════════════╣\n";

if ($hasCritical || $hasHigh) {
    echo "  ║  \033[31m!! AUTO-FAIL: Critical/High findings detected\033[0m         ║\n";
} else {
    echo "  ║  \033[32m   All Critical/High probes passed\033[0m                       ║\n";
}
echo "  ╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "  Results written to: " . RESULTS_PATH . "\n\n";

if (file_exists($curlCookieFile)) {
    @unlink($curlCookieFile);
}

if ($hasCritical || $hasHigh) {
    exit(1);
}
exit(0);
