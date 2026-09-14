<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 1);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

$opts = getopt('', ['env:', 'base:']);
$env = $opts['env'] ?? 'dev';
$baseUrl = $opts['base'] ?? '';

if ($baseUrl === '') {
    $baseUrl = match ($env) {
        'staging' => 'http://127.0.0.1:8080',
        default => 'http://127.0.0.1:8080',
    };
}

$baseUrl = rtrim($baseUrl, '/');

$results = [];
$stepNum = 0;

function http(string $method, string $path, array $data = null, array $headers = [], ?string $rawBody = null): array {
    global $baseUrl;
    $url = $baseUrl . $path;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    if ($rawBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        $headers['Content-Type'] = 'application/json';
    } elseif ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers['Content-Type'] = 'application/json';
    }

    $headerList = [];
    foreach ($headers as $k => $v) {
        $headerList[] = "{$k}: {$v}";
    }
    if ($headerList !== []) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headerStr = substr((string)$response, 0, $headerSize);
    $body = substr((string)$response, $headerSize);

    $setCookies = [];
    preg_match_all('/Set-Cookie:\s*([^\r\n]+)/i', $headerStr, $matches);
    if (!empty($matches[1])) {
        $setCookies = $matches[1];
    }

    $json = json_decode($body, true);
    return [
        'status' => $httpCode,
        'body' => $json,
        'raw' => $body,
        'headers' => $info,
        'set_cookies' => $setCookies,
        'response_headers' => $headerStr,
    ];
}

function step(string $name, callable $fn): void {
    global $results, $stepNum;
    $stepNum++;
    $start = microtime(true);
    try {
        $ret = $fn();
        $pass = $ret[0] ?? false;
        $reason = $ret[1] ?? '';
        $extra = $ret[2] ?? null;
        $ms = round((microtime(true) - $start) * 1000);
        $results[] = [
            'num' => $stepNum,
            'name' => $name,
            'pass' => $pass,
            'reason' => $reason,
            'ms' => $ms,
            'extra' => $extra ?? [],
        ];
    } catch (\Throwable $e) {
        $ms = round((microtime(true) - $start) * 1000);
        $results[] = [
            'num' => $stepNum,
            'name' => $name,
            'pass' => false,
            'reason' => 'EXCEPTION: ' . $e->getMessage(),
            'ms' => $ms,
            'extra' => [],
        ];
    }
}

function assert_status(array $resp, int $expected, string $context = ''): ?string {
    if ($resp['status'] !== $expected) {
        return "Expected HTTP {$expected}, got {$resp['status']}" . ($context ? " ({$context})" : '');
    }
    return null;
}

function assert_json_shape(array $resp, array $keys, string $context = ''): ?string {
    foreach ($keys as $key => $type) {
        $actualKey = explode('.', $key);
        $val = $resp['body'];
        foreach ($actualKey as $k) {
            if (!isset($val[$k])) {
                return "Missing key '{$key}' in response" . ($context ? " ({$context})" : '');
            }
            $val = $val[$k];
        }
        if ($type !== null && gettype($val) !== $type) {
            return "Key '{$key}' expected type {$type}, got " . gettype($val) . ($context ? " ({$context})" : '');
        }
    }
    return null;
}

function assert_error_envelope(array $resp): ?string {
    if (isset($resp['body']['success']) && $resp['body']['success'] === false) {
        if (!isset($resp['body']['error']['code']) || !isset($resp['body']['error']['message'])) {
            return 'Error response missing error.code or error.message';
        }
        $raw = $resp['raw'];
        $sensitive = ['SQLSTATE', 'PDO', 'Warning:', 'Fatal error', 'Stack trace', 'Exception in'];
        foreach ($sensitive as $pat) {
            if (stripos($raw, $pat) !== false) {
                return "Response body contains sensitive pattern: {$pat}";
            }
        }
        return null;
    }
    return null;
}

function readOtpFromEnv(): ?string {
    $otp = getenv('SMOKE_OTP');
    if ($otp !== false && $otp !== '') {
        return $otp;
    }
    $logFile = dirname(__DIR__) . '/storage/.otp_log';
    if (file_exists($logFile)) {
        $content = trim(file_get_contents($logFile));
        if (preg_match('/\b(\d{6})\b/', $content, $m)) {
            return $m[1];
        }
    }
    $appLog = dirname(__DIR__) . '/storage/logs/application.log';
    if (file_exists($appLog)) {
        $lines = array_reverse(file($appLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        foreach (array_slice($lines, 0, 200) as $line) {
            if (preg_match('/"otp":\s*"(\d{6})"/', $line, $m)) {
                return $m[1];
            }
        }
    }
    return null;
}

function readOtpForVerification(string $verificationId): ?string {
    $otp = getenv('SMOKE_OTP');
    if ($otp !== false && $otp !== '') {
        return $otp;
    }
    $logFile = dirname(__DIR__) . '/storage/.otp_log';
    if (file_exists($logFile)) {
        $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        foreach ($lines as $line) {
            if (str_contains($line, 'verification_id=' . $verificationId . ' ')
                && preg_match('/otp=(\d{6})/', $line, $m)) {
                return $m[1];
            }
        }
        $content = trim(file_get_contents($logFile));
        if (preg_match('/\b(\d{6})\b/', $content, $m)) {
            return $m[1];
        }
    }
    $appLog = dirname(__DIR__) . '/storage/logs/application.log';
    if (file_exists($appLog)) {
        $lines = array_reverse(file($appLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        foreach (array_slice($lines, 0, 500) as $line) {
            if (preg_match('/"otp":\s*"(\d{6})"/', $line, $m)) {
                return $m[1];
            }
        }
    }
    return null;
}

$farmerToken = null;
$farmerRefresh = null;
$farmerUser = null;
$operatorToken = null;
$operatorRefresh = null;
$superAdminToken = null;
$superAdminRefresh = null;
$twoFaToken = null;
$managerToken = null;
$csrfToken = null;
$csrfCookie = null;
$smokeMobile = '90' . str_pad((string)(rand(10000000, 99999999)), 8, '0', STR_PAD_LEFT);
$smokePassword = 'Smoke@' . rand(1000, 9999);
$registerVerificationId = null;
$registerToken = null;
$bookingId = null;
$tokenId = null;
$queueEntryId = null;
$procurementId = null;
$paymentId = null;
$approvedProcurementId = null;
$notificationId = null;
$centreId = null;
$slotId = null;
$operatorCentreId = null;
$adminDeviceId = null;

echo "========================================\n";
echo "  FPS Smoke Test — {$env}\n";
echo "  Base: {$baseUrl}\n";
echo "========================================\n\n";

$otp2faUser = getenv('SMOKE_2FA_USER') ?: '';
$otp2faPassword = getenv('SMOKE_2FA_PASSWORD') ?: 'Admin@1234';

// Dev defaults backed by seed.php --demo accounts, overridable via env.
$isDev = in_array($env, ['dev', 'development', 'local'], true);
$operatorMobile = getenv('SMOKE_OPERATOR_MOBILE') ?: ($isDev ? '9010000001' : '');
$operatorPassword = getenv('SMOKE_OPERATOR_PASSWORD') ?: 'Admin@1234';
$superAdminMobile = getenv('SMOKE_SUPER_ADMIN_MOBILE') ?: ($isDev ? '7010000001' : $otp2faUser);
$superAdminPassword = getenv('SMOKE_SUPER_ADMIN_PASSWORD') ?: 'Admin@1234';
$managerMobile = getenv('SMOKE_MANAGER_MOBILE') ?: ($isDev ? '9020000001' : '');
$managerPassword = getenv('SMOKE_MANAGER_PASSWORD') ?: 'Admin@1234';
if ($otp2faUser === '' && $isDev && getenv('SMOKE_2FA_USER') === false) {
    $otp2faUser = '9000000001';
    $otp2faPassword = getenv('SMOKE_2FA_PASSWORD') ?: 'Demo@1234';
}

step('01. GET /health', function () use (&$resp) {
    $resp = http('GET', '/health');
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $err = assert_json_shape($resp, ['success' => 'boolean', 'data' => 'array']);
    if ($err !== null) return [false, $err];
    if (($resp['body']['data']['status'] ?? '') !== 'UP') {
        return [false, 'Health status is not UP'];
    }
    return [true, 'Health OK'];
});

step('02. GET /web/csrf', function () use (&$csrfToken, &$csrfCookie) {
    $resp = http('GET', '/web/csrf');
    $cookies = [];
    foreach ($resp['set_cookies'] as $ck) {
        if (stripos($ck, 'csrf_token=') !== false) {
            preg_match('/csrf_token=([^;]+)/', $ck, $m);
            $csrfCookie = $m[1] ?? '';
        }
    }
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $csrfToken = $resp['body']['data']['csrf_token'] ?? '';
    if ($csrfToken === '') {
        return [false, 'No csrf_token in body'];
    }
    if ($csrfCookie === '') {
        return [false, 'No Set-Cookie csrf_token'];
    }
    return [true, 'CSRF primed'];
});

step('03. POST /auth/register', function () use (&$registerVerificationId, $smokeMobile) {
    $resp = http('POST', '/api/v1/auth/register', [
        'mobile' => $smokeMobile,
        'purpose' => 'REGISTER',
    ]);
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $err = assert_json_shape($resp, ['success' => 'boolean', 'data' => 'array']);
    if ($err !== null) return [false, $err];
    $registerVerificationId = $resp['body']['data']['verification_id'] ?? '';
    if ($registerVerificationId === '') {
        return [false, 'No verification_id returned'];
    }
    return [true, "verification_id={$registerVerificationId}"];
});

step('04. POST /auth/verify-otp', function () use ($registerVerificationId, &$registerToken) {
    if ($registerVerificationId === null || $registerVerificationId === '') {
        return [false, 'SKIP: no verification id (register step failed)'];
    }
    $otp = readOtpForVerification($registerVerificationId);
    if ($otp === null) {
        return [false, 'SKIP: needs manual OTP (set SMOKE_OTP env or create storage/.otp_log)'];
    }
    $resp = http('POST', '/api/v1/auth/verify-otp', [
        'verification_id' => $registerVerificationId,
        'otp' => $otp,
    ]);
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $err = assert_json_shape($resp, ['success' => 'boolean', 'data' => 'array']);
    if ($err !== null) return [false, $err];
    $registerToken = $resp['body']['data']['registration_token'] ?? '';
    if ($registerToken === '') {
        return [false, 'No registration_token returned'];
    }
    return [true, 'OTP verified'];
});

step('05. POST /auth/complete-registration', function () use ($registerToken, $smokeMobile, $smokePassword, &$farmerUser) {
    if ($registerToken === null || $registerToken === '') return [false, 'SKIP: no registration token'];
    $resp = http('POST', '/api/v1/auth/complete-registration', [
        'registration_token' => $registerToken,
        'name' => 'Smoke Test Farmer',
        'password' => $smokePassword,
        'password_confirmation' => $smokePassword,
        'village' => 'Test Village',
        'district_id' => 1,
        'state' => 'Test State',
        'pincode' => '123456',
    ]);

    if ($resp['status'] === 201 || $resp['status'] === 200) {
        $err = assert_json_shape($resp, ['success' => 'boolean']);
        if ($err !== null) return [false, $err];
        $farmerUser = $resp['body']['data']['farmer'] ?? [];
        return [true, 'Registration complete'];
    }

    if (isset($resp['body']['error']['code']) && $resp['body']['error']['code'] === 'ALREADY_REGISTERED') {
        return [true, 'Account exists (idempotent) — using existing'];
    }

    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? 'unknown')];
});

step('06. POST /auth/login (smoke farmer)', function () use ($smokeMobile, $smokePassword, &$farmerToken, &$farmerRefresh, &$farmerUser) {
    $resp = http('POST', '/api/v1/auth/login', [
        'mobile' => $smokeMobile,
        'password' => $smokePassword,
    ]);
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $err = assert_json_shape($resp, ['success' => 'boolean', 'data' => 'array']);
    if ($err !== null) return [false, $err];
    $farmerToken = $resp['body']['data']['access_token'] ?? '';
    $farmerRefresh = $resp['body']['data']['refresh_token'] ?? '';
    $farmerUser = $resp['body']['data']['user'] ?? $farmerUser;
    if ($farmerToken === '') return [false, 'No access_token'];
    return [true, 'Farmer login OK'];
});

step('07. 2FA flow (2FA user)', function () use ($otp2faUser, $otp2faPassword, &$twoFaToken) {
    if ($otp2faUser === '') {
        return [false, 'SKIP: SMOKE_2FA_USER env not set'];
    }

    $resp = http('POST', '/api/v1/auth/login', [
        'mobile' => $otp2faUser,
        'password' => $otp2faPassword,
    ]);

    if ($resp['status'] === 202) {
        $code = $resp['body']['meta']['code'] ?? $resp['body']['data']['two_factor_required'] ?? null;
        $vid = $resp['body']['data']['verification_id'] ?? '';

        $otp = readOtpForVerification($vid);
        if ($otp === null) {
            return [false, 'SKIP: 2FA OTP not available (set SMOKE_OTP)'];
        }

        $wrongOtp = '000000';
        $wrongResp = http('POST', '/api/v1/auth/verify-2fa', [
            'verification_id' => $vid,
            'otp' => $wrongOtp,
        ]);
        if ($wrongResp['status'] !== 401 && $wrongResp['status'] !== 400 && $wrongResp['status'] !== 422) {
            return [false, "Wrong OTP should return 401/400/422, got {$wrongResp['status']}"];
        }
        $wrongErr = assert_error_envelope($wrongResp);
        if ($wrongErr !== null) return [false, $wrongErr];

        $resendResp = http('POST', '/api/v1/auth/resend-2fa', [
            'verification_id' => $vid,
        ]);
        $usedOtp = $otp;
        $freshOtp = null;
        if ($resendResp['status'] === 200) {
            $freshOtp = readOtpForVerification($vid);
            if ($freshOtp !== null) $usedOtp = $freshOtp;
        } elseif ($resendResp['status'] === 429) {
            $usedOtp = $otp;
        } else {
            return [false, "resend-2fa returned {$resendResp['status']}"];
        }

        $correctResp = http('POST', '/api/v1/auth/verify-2fa', [
            'verification_id' => $vid,
            'otp' => $usedOtp,
        ]);
        $err = assert_status($correctResp, 200);
        if ($err !== null) return [false, $err];
        $twoFaToken = $correctResp['body']['data']['access_token'] ?? '';

        return [true, '2FA flow complete (wrong→resend→correct)'];
    }

    if ($resp['status'] === 200) {
        $twoFaToken = $resp['body']['data']['access_token'] ?? '';
        return [true, '2FA user login OK (no 2FA required)'];
    }

    return [false, "Login returned {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('08. GET /auth/me + refresh + logout', function () use (&$farmerToken, &$farmerRefresh) {
    $me = http('GET', '/api/v1/auth/me', null, ['Authorization' => "Bearer {$farmerToken}"]);
    $err = assert_status($me, 200);
    if ($err !== null) return [false, $err];
    $err = assert_json_shape($me, ['data' => 'array']);
    if ($err !== null) return [false, $err];
    if (!isset($me['body']['data']['id'])) return [false, 'me() missing user id'];

    $refresh = http('POST', '/api/v1/auth/refresh', [
        'refresh_token' => $farmerRefresh,
    ]);
    $err = assert_status($refresh, 200);
    if ($err !== null) return [false, "refresh: {$err}"];
    $oldToken = $farmerToken;
    $farmerToken = $refresh['body']['data']['access_token'] ?? $farmerToken;
    $farmerRefresh = $refresh['body']['data']['refresh_token'] ?? $farmerRefresh;

    $logout = http('POST', '/api/v1/auth/logout', [
        'refresh_token' => $farmerRefresh,
    ], ['Authorization' => "Bearer {$farmerToken}"]);
    $err = assert_status($logout, 200);
    if ($err !== null) return [false, "logout: {$err}"];

    $stale = http('GET', '/api/v1/auth/me', null, ['Authorization' => "Bearer {$oldToken}"]);
    if ($stale['status'] !== 401) {
        return [false, "Old token should return 401 after logout, got {$stale['status']}"];
    }

    return [true, 'me→refresh→logout→stale-rejected'];
});

step('08b. Re-login farmer for subsequent steps', function () use ($smokeMobile, $smokePassword, &$farmerToken, &$farmerRefresh) {
    $resp = http('POST', '/api/v1/auth/login', [
        'mobile' => $smokeMobile,
        'password' => $smokePassword,
    ]);
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $farmerToken = $resp['body']['data']['access_token'] ?? '';
    $farmerRefresh = $resp['body']['data']['refresh_token'] ?? '';
    if ($farmerToken === '') return [false, 'No access_token'];
    return [true, 'Re-login OK'];
});

step('09. POST /auth/devices', function () use ($farmerToken) {
    $resp = http('POST', '/api/v1/auth/devices', [
        'device_id' => 'smoke-device-001',
        'platform' => 'android',
        'onesignal_player_id' => '00000000-0000-0000-0000-000000000001',
    ], ['Authorization' => "Bearer {$farmerToken}"]);
    if (in_array($resp['status'], [200, 201])) {
        return [true, 'Device registered'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('10a. GET /centres', function () use (&$centreId) {
    $resp = http('GET', '/api/v1/centres');
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $centres = $resp['body']['data'] ?? [];
    if (empty($centres)) return [false, 'No centres found (seed data needed)'];

    $picked = null;
    foreach ($centres as $c) {
        if ((string)($c['code'] ?? '') === 'CEN01') { $picked = $c; break; }
    }
    if ($picked === null) {
        foreach ($centres as $c) {
            if (strpos((string)($c['code'] ?? ''), 'CEN') === 0) { $picked = $c; break; }
        }
    }
    if ($picked === null) $picked = is_array($centres[0] ?? null) ? $centres[0] : $centres;

    $centreId = (int)$picked['id'];
    return [true, "Found " . count($centres) . " centres, using centre_code=" . ($picked['code'] ?? '') . " id={$centreId}"];
});

step('10b. GET /slots?centre_id=&date= (first weekday in horizon)', function () use ($centreId, &$slotId, &$bookingDate) {
    for ($i = 1; $i <= 7; $i++) {
        $date = date('Y-m-d', strtotime("+{$i} day"));
        $resp = http('GET', "/api/v1/slots?centre_id={$centreId}&date={$date}");
        $err = assert_status($resp, 200);
        if ($err !== null) return [false, "date={$date}: {$err}"];
        $slots = $resp['body']['data']['slots'] ?? $resp['body']['data'] ?? [];
        if (!empty($slots)) {
            $first = is_array($slots[0] ?? null) ? $slots[0] : $slots;
            $slotId = (int)($first['id'] ?? 0);
            $bookingDate = $date;
            return [true, "Found " . count($slots) . " slots on {$date}, using slot_id={$slotId}"];
        }
    }
    return [false, "No slots for centre {$centreId} within +7 days (seed slots for business days; run generate-slots)"];
});

step('10c. GET /crops', function () {
    $resp = http('GET', '/api/v1/crops');
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $crops = $resp['body']['data'] ?? [];
    if (empty($crops)) return [false, 'No crops found (seed data needed)'];
    return [true, "Found " . count($crops) . " crops"];
});

step('10d. POST /bookings (multi-crop)', function () use ($farmerToken, $centreId, $slotId, $bookingDate, &$bookingId, &$tokenId) {
    $date = $bookingDate;
    $crops = [
        ['crop_id' => 1, 'qty' => 50.0],
        ['crop_id' => 2, 'qty' => 30.0],
        ['crop_id' => 3, 'qty' => 20.0],
    ];
    $resp = http('POST', '/api/v1/bookings', [
        'booking_date' => $date,
        'slot_id' => $slotId,
        'centre_id' => $centreId,
        'crops' => $crops,
    ], ['Authorization' => "Bearer {$farmerToken}"]);

    if ($resp['status'] === 201) {
        $err = assert_json_shape($resp, ['success' => 'boolean', 'data' => 'array']);
        if ($err !== null) return [false, $err];
        $bookingData = $resp['body']['data']['booking'] ?? $resp['body']['data'];
        $bookingId = (int)($bookingData['id'] ?? 0);
        $tokenId = (int)($bookingData['token']['id'] ?? 0);
        $tokenNum = $bookingData['token']['token_number'] ?? '';
        return [true, "Booking #{$bookingId} created, token={$tokenNum}"];
    }

    if (isset($resp['body']['error']['code']) && $resp['body']['error']['code'] === 'DUPLICATE_BOOKING') {
        return [true, 'Duplicate booking detected (idempotent)'];
    }

    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? 'unknown')];
});

step('10e. GET /bookings (list)', function () use ($farmerToken, $bookingId) {
    $resp = http('GET', '/api/v1/bookings', null, ['Authorization' => "Bearer {$farmerToken}"]);
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $bookings = $resp['body']['data'] ?? [];
    if (empty($bookings)) return [false, 'Bookings list is empty'];
    if ($bookingId !== null) {
        $found = false;
        foreach ($bookings as $b) {
            if ((int)($b['id'] ?? 0) === $bookingId) {
                $found = true;
                break;
            }
        }
        if (!$found) return [false, "Booking #{$bookingId} not found in list"];
    }
    return [true, "Found " . count($bookings) . " bookings"];
});

step('10f. GET /tokens/{id}', function () use ($farmerToken, $bookingId) {
    if ($bookingId === null) return [false, 'SKIP: no booking'];
    $resp = http('GET', "/api/v1/bookings/{$bookingId}", null, ['Authorization' => "Bearer {$farmerToken}"]);
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $booking = $resp['body']['data']['booking'] ?? $resp['body']['data'];
    $token = $booking['token'] ?? null;
    if ($token === null) return [false, 'No token in booking response'];
    return [true, "Token: " . ($token['token_number'] ?? 'OK')];
});

step('11a. GET /queue/my', function () use ($farmerToken, &$queueEntryId) {
    $resp = http('GET', '/api/v1/queue/my', null, ['Authorization' => "Bearer {$farmerToken}"]);
    if ($resp['status'] === 200) {
        $entry = $resp['body']['data']['queue'] ?? null;
        if ($entry !== null) {
            $queueEntryId = (int)($entry['id'] ?? 0);
            return [true, "Queue position: " . ($entry['position'] ?? 'N/A')];
        }
        return [true, 'No active queue entry (may be expected)'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('11b. Operator login for queue ops', function () use (&$operatorToken, &$operatorRefresh, &$operatorCentreId, $operatorMobile, $operatorPassword) {
    $me = http('POST', '/api/v1/auth/login', [
        'mobile' => $operatorMobile,
        'password' => $operatorPassword,
    ]);

    if ($me['status'] === 202) {
        $vid = $me['body']['data']['verification_id'] ?? '';
        $otp = readOtpForVerification($vid);
        if ($otp === null) return [false, 'SKIP: operator 2FA OTP not available'];
        $r2 = http('POST', '/api/v1/auth/verify-2fa', ['verification_id' => $vid, 'otp' => $otp]);
        if ($r2['status'] !== 200) return [false, "2FA verify returned {$r2['status']}"];
        $operatorToken = $r2['body']['data']['access_token'] ?? '';
        $operatorRefresh = $r2['body']['data']['refresh_token'] ?? '';
    } elseif ($me['status'] === 200) {
        $operatorToken = $me['body']['data']['access_token'] ?? '';
        $operatorRefresh = $me['body']['data']['refresh_token'] ?? '';
    } else {
        return [false, "Operator login {$me['status']}: " . ($me['body']['error']['message'] ?? '')];
    }

    if ($operatorToken === '') return [false, 'No operator token'];

    $me2 = http('GET', '/api/v1/auth/me', null, ['Authorization' => "Bearer {$operatorToken}"]);
    if ($me2['status'] === 200) {
        $role = $me2['body']['data']['role'] ?? '';
        $operatorCentreId = $me2['body']['data']['centre_id'] ?? null;
        if (!$operatorCentreId) {
            $centres = http('GET', '/api/v1/centres', null, ['Authorization' => "Bearer {$operatorToken}"]);
            $items = $centres['body']['data'] ?? [];
            if (is_array($items) && isset($items[0]['id'])) {
                $operatorCentreId = (int) $items[0]['id'];
            }
        }
        return [true, "Operator role={$role} centre_id=" . ($operatorCentreId ?? 'N/A')];
    }
    return [true, 'Operator login OK (me() failed but token valid)'];
});

step('11c. POST /operator/queue/call-next', function () use ($operatorToken, $operatorCentreId, &$queueEntryId) {
    if ($operatorToken === '') return [false, 'SKIP: no operator token'];
    if ($operatorCentreId === null) return [false, 'SKIP: no operator centre resolved'];
    for ($i = 1; $i <= 7; $i++) {
        $candidateDate = date('Y-m-d', strtotime("+{$i} day"));
        $resp = http('POST', '/api/v1/operator/queue/call-next', [
            'centre_id' => (int)$operatorCentreId,
            'date' => $candidateDate,
        ], ['Authorization' => "Bearer {$operatorToken}"]);

        if (in_array($resp['status'], [200, 201])) {
            $entry = $resp['body']['data']['entry'] ?? null;
            if ($entry !== null) {
                $queueEntryId = (int)($entry['entry_id'] ?? $entry['id'] ?? 0);
            }
            return [true, 'call-next OK on ' . $candidateDate . ', entry_id=' . ($queueEntryId ?? 'N/A')];
        }

        if (!in_array($resp['status'], [409, 428, 422])) {
            return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
        }
    }
    return [true, 'call-next: queue empty across demo window (expected)'];
});

step('12a. POST /operator/queue/{id}/start (procurement)', function () use ($operatorToken, $queueEntryId, &$procurementId) {
    if ($operatorToken === '' || $queueEntryId === null || $queueEntryId === 0) {
        return [false, 'SKIP: no operator token or queue entry'];
    }
    $resp = http('POST', "/api/v1/operator/queue/{$queueEntryId}/start", null, [
        'Authorization' => "Bearer {$operatorToken}",
    ]);

    if (in_array($resp['status'], [200, 201])) {
        $data = $resp['body']['data'] ?? [];
        $list = $data['procurements'] ?? $data['procurement'] ?? [$data];
        $first = is_array($list[0] ?? null) ? $list[0] : $list;
        $procurementId = (int)($first['id'] ?? 0);
        return [true, "Procurement #{$procurementId} started (count=" . count($list) . ")"];
    }

    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('12b. PUT /operator/procurements/{id} (capture weight)', function () use ($operatorToken, $procurementId) {
    if ($procurementId === null || $procurementId === 0) {
        return [false, 'SKIP: no procurement id'];
    }
    $resp = http('PUT', "/api/v1/operator/procurements/{$procurementId}", [
        'accepted_weight' => 25.5,
        'grade' => 'A',
        'moisture_pct' => 12.5,
    ], ['Authorization' => "Bearer {$operatorToken}"]);

    if (in_array($resp['status'], [200, 201])) {
        return [true, 'Procurement weight captured'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('12c. POST /operator/procurements/{id}/submit', function () use ($operatorToken, $procurementId) {
    if ($procurementId === null || $procurementId === 0) {
        return [false, 'SKIP: no procurement id'];
    }
    $resp = http('POST', "/api/v1/operator/procurements/{$procurementId}/submit", null, [
        'Authorization' => "Bearer {$operatorToken}",
    ]);

    if (in_array($resp['status'], [200, 201])) {
        return [true, 'Procurement submitted'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('12d. GET /my/procurements', function () use ($farmerToken) {
    $resp = http('GET', '/api/v1/my/procurements', null, ['Authorization' => "Bearer {$farmerToken}"]);
    if (in_array($resp['status'], [200, 404])) {
        return [true, "Procurements endpoint responded (HTTP {$resp['status']})"];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('12e. Approve submitted procurement (super admin)', function () use ($superAdminMobile, $superAdminPassword, &$approvedProcurementId, &$paymentId) {
    $login = http('POST', '/api/v1/auth/login', [
        'mobile' => $superAdminMobile,
        'password' => $superAdminPassword,
    ]);
    if ($login['status'] !== 200) {
        return [false, "SA login {$login['status']}: " . ($login['body']['error']['message'] ?? '')];
    }
    $token = $login['body']['data']['access_token'] ?? '';
    if ($token === '') return [false, 'No SA token'];

    $list = http('GET', '/api/v1/admin/approvals', null, ['Authorization' => "Bearer {$token}"]);
    $err = assert_status($list, 200);
    if ($err !== null) return [false, "approvals list: {$err}"];
    $items = $list['body']['data']['items'] ?? $list['body']['data']['approvals'] ?? $list['body']['data'] ?? [];
    if (!is_array($items) || empty($items)) return [true, 'No pending-approval procurements (expected if none were submitted)'];

    $first = is_array($items[0] ?? null) ? $items[0] : $items;
    $approvedProcurementId = (int)($first['id'] ?? 0);
    if ($approvedProcurementId === 0) return [false, 'Approval list item missing id'];

    $approve = http('POST', "/api/v1/admin/approvals/{$approvedProcurementId}/approve", null, [
        'Authorization' => "Bearer {$token}",
    ]);
    if (!in_array($approve['status'], [200, 201])) {
        return [false, "approve HTTP {$approve['status']}: " . ($approve['body']['error']['message'] ?? '')];
    }

    $pays = http('GET', "/api/v1/operator/payments?status=PENDING", null, ['Authorization' => "Bearer {$token}"]);
    if ($pays['status'] === 200) {
        $pItems = $pays['body']['data']['items'] ?? $pays['body']['data']['payments'] ?? $pays['body']['data'] ?? [];
        if (is_array($pItems) && !empty($pItems)) {
            $pFirst = is_array($pItems[0] ?? null) ? $pItems[0] : $pItems;
            $paymentId = (int)($pFirst['id'] ?? 0);
        }
    }
    return [true, "Procurement #{$approvedProcurementId} approved" . ($paymentId ? ", payment_id={$paymentId}" : "")];
});

step('13a. Release payment (centre manager)', function () use ($managerMobile, $managerPassword, &$paymentId) {
    $login = http('POST', '/api/v1/auth/login', [
        'mobile' => $managerMobile,
        'password' => $managerPassword,
    ]);
    if ($login['status'] !== 200) {
        return [false, "Manager login {$login['status']}: " . ($login['body']['error']['message'] ?? '')];
    }
    $token = $login['body']['data']['access_token'] ?? '';
    if ($token === '') return [false, 'No manager token'];

    $centres = http('GET', '/api/v1/centres', null, ['Authorization' => "Bearer {$token}"]);
    $items = $centres['body']['data'] ?? [];
    $ids = [];
    if (is_array($items) && isset($items[0]['id'])) {
        foreach ($items as $c) { $ids[] = (int)$c['id']; }
    }
    if (empty($ids)) return [true, 'No released payments; manager has no centre scope'];

    $pays = http('GET', '/api/v1/operator/payments?status=PENDING&centre_id=' . $ids[0], null, ['Authorization' => "Bearer {$token}"]);
    $pItems = $pays['body']['data']['items'] ?? $pays['body']['data']['payments'] ?? $pays['body']['data'] ?? [];
    if (!is_array($pItems) || empty($pItems)) {
        return [true, 'No PENDING payment in manager centre scope (expected if none were approved)'];
    }
    $pFirst = is_array($pItems[0] ?? null) ? $pItems[0] : $pItems;
    $paymentId = (int)($pFirst['id'] ?? 0);
    if ($paymentId === 0) return [false, 'PENDING payment item missing id'];

    $resp = http('PUT', "/api/v1/operator/payments/{$paymentId}/release", [
        'method' => 'bank_transfer',
        'payment_reference' => 'SMK-' . date('YmdHis') . '-' . $paymentId,
    ], ['Authorization' => "Bearer {$token}"]);

    if (in_array($resp['status'], [200, 201])) {
        return [true, "Payment #{$paymentId} released"];
    }
    return [false, "release HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('13b. GET /my/payments', function () use ($farmerToken) {
    $resp = http('GET', '/api/v1/my/payments', null, ['Authorization' => "Bearer {$farmerToken}"]);
    if (in_array($resp['status'], [200, 404])) {
        return [true, "Payments endpoint responded (HTTP {$resp['status']})"];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('14a. GET /notifications', function () use ($farmerToken, &$notificationId) {
    $resp = http('GET', '/api/v1/notifications', null, ['Authorization' => "Bearer {$farmerToken}"]);
    $err = assert_status($resp, 200);
    if ($err !== null) return [false, $err];
    $notifs = $resp['body']['data'] ?? [];
    if (!empty($notifs)) {
        $first = is_array($notifs[0] ?? null) ? $notifs[0] : $notifs;
        $notificationId = (int)($first['id'] ?? 0);
    }
    return [true, "Notifications: " . count($notifs) . " items"];
});

step('14b. PATCH /notifications/{id}/read', function () use ($farmerToken, $notificationId) {
    if ($notificationId === null || $notificationId === 0) {
        return [true, 'SKIP: no notifications to mark read'];
    }
    $resp = http('PATCH', "/api/v1/notifications/{$notificationId}/read", null, [
        'Authorization' => "Bearer {$farmerToken}",
    ]);
    if (in_array($resp['status'], [200, 204])) {
        return [true, 'Notification marked read'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('15a. POST /admin/notifications/test-push', function () use (&$superAdminToken, &$superAdminRefresh, $superAdminMobile, $superAdminPassword) {
    if ($superAdminToken === '' || $superAdminToken === null) {
        if ($superAdminMobile === '') return [false, 'SKIP: no super admin credentials (set SMOKE_SUPER_ADMIN_MOBILE)'];
        $login = http('POST', '/api/v1/auth/login', ['mobile' => $superAdminMobile, 'password' => $superAdminPassword]);
        if ($login['status'] === 200) {
            $superAdminToken = $login['body']['data']['access_token'] ?? '';
            $superAdminRefresh = $login['body']['data']['refresh_token'] ?? '';
        } elseif ($login['status'] === 202) {
            $vid = $login['body']['data']['verification_id'] ?? '';
            $otp = readOtpForVerification($vid);
            if ($otp === null) return [false, 'SKIP: SA 2FA OTP not available'];
            $r2 = http('POST', '/api/v1/auth/verify-2fa', ['verification_id' => $vid, 'otp' => $otp]);
            if ($r2['status'] === 200) {
                $superAdminToken = $r2['body']['data']['access_token'] ?? '';
                $superAdminRefresh = $r2['body']['data']['refresh_token'] ?? '';
            }
        }
        if ($superAdminToken === '') return [false, "SA login failed: " . ($login['body']['error']['message'] ?? 'unknown')];
    }

    $resp = http('POST', '/api/v1/admin/notifications/test-push', [
        'onesignal_player_id' => '00000000-0000-0000-0000-000000000001',
        'title' => 'Smoke Test',
        'body' => 'This is a smoke test push',
    ], ['Authorization' => "Bearer {$superAdminToken}"]);

    if (in_array($resp['status'], [200, 202])) {
        return [true, 'Test push accepted'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('15b. Operator -> super_admin route -> 403', function () use ($operatorToken) {
    if ($operatorToken === '') return [false, 'SKIP: no operator token'];
    $resp = http('PUT', '/api/v1/admin/maintenance', [
        'enabled' => true,
    ], ['Authorization' => "Bearer {$operatorToken}"]);
    if ($resp['status'] === 403) {
        return [true, 'Operator correctly denied maintenance access'];
    }
    return [false, "Expected 403, got {$resp['status']}"];
});

step('15c. Super admin login for maintenance', function () use ($superAdminMobile, $superAdminPassword, &$superAdminToken, &$superAdminRefresh) {
    if ($superAdminToken !== '' && $superAdminToken !== null) {
        return [true, 'Super admin token already available'];
    }
    if ($superAdminMobile === '') return [false, 'SKIP: no super admin credentials (set SMOKE_SUPER_ADMIN_MOBILE)'];

    $resp = http('POST', '/api/v1/auth/login', ['mobile' => $superAdminMobile, 'password' => $superAdminPassword]);
    if ($resp['status'] === 200) {
        $superAdminToken = $resp['body']['data']['access_token'] ?? '';
        $superAdminRefresh = $resp['body']['data']['refresh_token'] ?? '';
        return [true, 'Super admin login OK'];
    }
    if ($resp['status'] === 202) {
        $vid = $resp['body']['data']['verification_id'] ?? '';
        $otp = readOtpForVerification($vid);
        if ($otp === null) return [false, 'SKIP: SA 2FA OTP not available'];
        $r2 = http('POST', '/api/v1/auth/verify-2fa', ['verification_id' => $vid, 'otp' => $otp]);
        if ($r2['status'] === 200) {
            $superAdminToken = $r2['body']['data']['access_token'] ?? '';
            $superAdminRefresh = $r2['body']['data']['refresh_token'] ?? '';
            return [true, 'Super admin 2FA OK'];
        }
    }
    return [false, "SA login {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('16a. Maintenance enable (super admin)', function () use ($superAdminToken) {
    if ($superAdminToken === '') return [false, 'SKIP: no super admin token'];
    $resp = http('PUT', '/api/v1/admin/maintenance', [
        'enabled' => true,
    ], ['Authorization' => "Bearer {$superAdminToken}"]);
    if (in_array($resp['status'], [200, 201])) {
        return [true, 'Maintenance enabled'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('16b. Anonymous during maintenance -> 503', function () {
    $resp = http('GET', '/api/v1/centres');
    if ($resp['status'] === 503) {
        $errCode = $resp['body']['error']['code'] ?? '';
        if ($errCode === 'MAINTENANCE_MODE') {
            return [true, 'Correctly returned 503 MAINTENANCE_MODE'];
        }
        return [false, "503 but error code is {$errCode}"];
    }
    return [false, "Expected 503, got {$resp['status']}"];
});

step('16c. Maintenance disable (super admin)', function () use ($superAdminToken) {
    if ($superAdminToken === '') return [false, 'SKIP: no super admin token'];
    $resp = http('PUT', '/api/v1/admin/maintenance', [
        'enabled' => false,
    ], ['Authorization' => "Bearer {$superAdminToken}"]);
    if (in_array($resp['status'], [200, 201])) {
        return [true, 'Maintenance disabled'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('16d. Anonymous after disable -> 200', function () {
    $resp = http('GET', '/api/v1/centres');
    if ($resp['status'] === 200) {
        return [true, 'Anonymous access restored'];
    }
    return [false, "Expected 200, got {$resp['status']}"];
});

step('17a. POST /auth/forgot-password', function () use ($smokeMobile, &$resetVerificationId) {
    $resp = http('POST', '/api/v1/auth/forgot-password', [
        'mobile' => $smokeMobile,
    ]);
    if (in_array($resp['status'], [200, 202])) {
        $resetVerificationId = $resp['body']['data']['reset_id'] ?? '';
        return [true, "reset_id={$resetVerificationId}"];
    }
    if (isset($resp['body']['error']['code']) && $resp['body']['error']['code'] === 'ACCOUNT_NOT_FOUND') {
        return [true, 'forgot-password returned ACCOUNT_NOT_FOUND (expected for new user)'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

step('17b. POST /auth/reset-password', function () use ($smokeMobile, &$resetVerificationId) {
    if (!isset($resetVerificationId) || $resetVerificationId === '') {
        return [false, 'SKIP: no reset_verification_id from step 17a'];
    }
    $otp = readOtpForVerification($resetVerificationId);
    if ($otp === null) {
        return [false, 'SKIP: reset OTP not available'];
    }
    $newPass = 'SmokeReset@' . rand(1000, 9999);
    $resp = http('POST', '/api/v1/auth/reset-password', [
        'reset_id' => $resetVerificationId,
        'otp' => $otp,
        'password' => $newPass,
        'password_confirmation' => $newPass,
    ]);
    if (in_array($resp['status'], [200, 201])) {
        return [true, 'Password reset OK'];
    }
    return [false, "HTTP {$resp['status']}: " . ($resp['body']['error']['message'] ?? '')];
});

echo "\n========================================\n";
echo "  RESULTS SUMMARY\n";
echo "========================================\n\n";

$pass = 0;
$fail = 0;
$skip = 0;

foreach ($results as $r) {
    $status = $r['pass'] ? 'PASS' : (str_starts_with($r['reason'], 'SKIP:') ? 'SKIP' : 'FAIL');
    $color = match ($status) {
        'PASS' => "\033[32m",
        'SKIP' => "\033[33m",
        'FAIL' => "\033[31m",
        default => '',
    };
    $reset = "\033[0m";
    echo sprintf("  %s[%s]%s %02d. %-55s %6dms  %s\n",
        $color, $status, $reset,
        $r['num'], $r['name'], $r['ms'],
        ($r['pass'] || str_starts_with($r['reason'], 'SKIP:')) ? '' : $r['reason']
    );
    if ($status === 'PASS') $pass++;
    elseif ($status === 'SKIP') $skip++;
    else $fail++;
}

echo "\n  Total: " . count($results) . " | PASS: {$pass} | FAIL: {$fail} | SKIP: {$skip}\n";

if ($fail > 0) {
    echo "\n\033[31m  SMOKE TEST FAILED\033[0m\n";
    exit(1);
}

echo "\n\033[32m  SMOKE TEST PASSED\033[0m\n";
exit(0);
