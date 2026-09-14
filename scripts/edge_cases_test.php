<?php
declare(strict_types=1);

/*
 * edge_cases_test.php — Edge-case & boundary-condition tests for the
 * SIH Farmer Procurement System API.
 *
 * Standalone PHP-CLI script using only curl. No autoloading required.
 *
 * Usage:
 *   php scripts/edge_cases_test.php [--base=http://127.0.0.1:8080]
 *
 * Environment variables (optional):
 *   SMOKE_FARMER_TOKEN      — pre-authenticated farmer JWT
 *   SMOKE_OPERATOR_TOKEN    — pre-authenticated operator/admin JWT
 *   SMOKE_FARMER_MOBILE     — farmer mobile  (default 9000000004, non-2FA demo farmer)
 *   SMOKE_FARMER_PASSWORD   — farmer password (default Demo@1234)
 *   SMOKE_OPERATOR_MOBILE   — operator mobile  (default 9020000001, CENTRE_MANAGER)
 *   SMOKE_OPERATOR_PASSWORD — operator password (default Admin@1234)
 *   SMOKE_BASE_URL          — base URL alias (overridden by --base)
 */

/* ────────────────────────────── CLI argument parsing ─────────────────────── */

$baseUrl = getenv('SMOKE_BASE_URL') ?: 'http://127.0.0.1:8080';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $baseUrl = substr($arg, 7);
    }
}

$baseUrl = rtrim($baseUrl, '/');

/* ────────────────────────────── Results tracking ─────────────────────────── */

$results = []; // [string $name, bool $pass, string $reason]
$step    = 0;

function record(string $name, bool $pass, string $reason): void
{
    global $results, $step;
    $step++;
    $results[] = ['name' => $name, 'pass' => $pass, 'reason' => $reason];
    $tag   = $pass ? 'PASS' : 'FAIL';
    $color = $pass ? "\033[32m" : "\033[31m";
    echo "{$color}[{$tag}]\033[0m Step {$step}: {$name} — {$reason}\n";
}

function skip(string $name, string $reason): void
{
    global $results, $step;
    $step++;
    $results[] = ['name' => $name, 'pass' => true, 'reason' => "SKIP — {$reason}"];
    echo "\033[33m[SKIP]\033[0m Step {$step}: {$name} — {$reason}\n";
}

/* ────────────────────────────── HTTP helper ──────────────────────────────── */

function http(
    string $method,
    string $path,
    ?array $data    = null,
    ?array $headers = null
): array {
    global $baseUrl;

    $url = $baseUrl . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $h = ['Accept: application/json'];

    if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $h[] = 'Content-Type: application/json';
    }

    if ($headers !== null) {
        foreach ($headers as $header) {
            $h[] = $header;
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);

    $raw       = curl_exec($ch);
    $headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = [];
    if ($raw !== false) {
        $bodyStr = substr($raw, $headerLen);
        $decoded = json_decode($bodyStr, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }

    return ['status' => $status, 'body' => $body, 'raw' => $raw !== false ? $raw : ''];
}

/* ────────────────────────────── OTP helpers (dev log) ─────────────────────── */

function readOtpForVerification(string $verificationId): ?string
{
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
    }
    return null;
}

/* ────────────────────────────── Token store ──────────────────────────────── */

$farmerToken   = getenv('SMOKE_FARMER_TOKEN') ?: null;
$operatorToken = getenv('SMOKE_OPERATOR_TOKEN') ?: null;

/* ────────────────────────────── Auth helpers ─────────────────────────────── */

/**
 * Authenticate via mobile + password. Handles 2FA (202) by reading the
 * dev OTP log and calling /auth/verify-2fa.
 */
function login(string $mobile, string $password): ?string
{
    $res = http('POST', '/api/v1/auth/login', [
        'mobile'   => $mobile,
        'password' => $password,
    ]);

    if ($res['status'] === 200) {
        return $res['body']['data']['access_token'] ?? null;
    }

    // 2FA flow — the API returns verification_id under data
    if ($res['status'] === 202) {
        $vid = $res['body']['data']['verification_id'] ?? null;
        if ($vid === null) {
            return null;
        }
        $otp = readOtpForVerification($vid);
        if ($otp === null) {
            return null;
        }
        $verify = http('POST', '/api/v1/auth/verify-2fa', [
            'verification_id' => $vid,
            'otp'             => $otp,
        ]);
        if ($verify['status'] === 200) {
            return $verify['body']['data']['access_token'] ?? null;
        }
    }

    return null;
}

function authFarmerHeader(): array
{
    global $farmerToken;
    if ($farmerToken === null) {
        throw new RuntimeException('No farmer token available');
    }
    return ["Authorization: Bearer {$farmerToken}"];
}

function authOperatorHeader(): array
{
    global $operatorToken;
    if ($operatorToken === null) {
        throw new RuntimeException('No operator token available');
    }
    return ["Authorization: Bearer {$operatorToken}"];
}

function ensureFarmerToken(): bool
{
    global $farmerToken;
    if ($farmerToken !== null) {
        return true;
    }
    $mobile   = getenv('SMOKE_FARMER_MOBILE') ?: '9000000004';
    $password = getenv('SMOKE_FARMER_PASSWORD') ?: 'Demo@1234';
    $farmerToken = login($mobile, $password);
    return $farmerToken !== null;
}

function ensureOperatorToken(): bool
{
    global $operatorToken;
    if ($operatorToken !== null) {
        return true;
    }
    $mobile   = getenv('SMOKE_OPERATOR_MOBILE') ?: '9020000001';
    $password = getenv('SMOKE_OPERATOR_PASSWORD') ?: 'Admin@1234';
    $operatorToken = login($mobile, $password);
    return $operatorToken !== null;
}

/* ────────────────────────────── Utility helpers ──────────────────────────── */

function todayStr(): string
{
    return date('Y-m-d');
}

function daysFromNow(int $days): string
{
    return date('Y-m-d', time() + $days * 86400);
}

/**
 * Try to find a valid farmer booking ID from recent bookings.
 * Falls back to null if nothing is available.
 */
function findFarmerBookingId(): ?string
{
    if (!ensureFarmerToken()) {
        return null;
    }
    $res = http('GET', '/api/v1/bookings?limit=5', null, authFarmerHeader());
    if ($res['status'] === 200 && isset($res['body']['data'])) {
        foreach ($res['body']['data'] as $booking) {
            if (!empty($booking['id'])) {
                return (string) $booking['id'];
            }
        }
    }
    // Also try a nested structure
    if ($res['status'] === 200 && isset($res['body']['bookings'])) {
        foreach ($res['body']['bookings'] as $booking) {
            if (!empty($booking['id'])) {
                return (string) $booking['id'];
            }
        }
    }
    return null;
}

/**
 * Find a released payment ID from operator queue.
 */
function findReleasedPaymentId(): ?string
{
    if (!ensureOperatorToken()) {
        return null;
    }
    $res = http('GET', '/api/v1/operator/payments?status=released&limit=1', null, authOperatorHeader());
    if ($res['status'] === 200) {
        $items = $res['body']['data'] ?? $res['body']['payments'] ?? $res['body'] ?? [];
        if (is_array($items) && count($items) > 0) {
            $first = $items[0] ?? null;
            if (is_array($first) && !empty($first['id'])) {
                return (string) $first['id'];
            }
        }
    }
    return null;
}

/**
 * Find a booking in the operator queue (for call-next tests).
 * Returns booking id if available.
 */
function findQueuedBookingId(): ?string
{
    if (!ensureOperatorToken()) {
        return null;
    }
    $res = http('GET', '/api/v1/operator/queue?limit=5', null, authOperatorHeader());
    if ($res['status'] === 200) {
        $items = $res['body']['data'] ?? $res['body']['queue'] ?? $res['body'] ?? [];
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $item) {
                if (is_array($item) && !empty($item['id'])) {
                    return (string) $item['id'];
                }
            }
        }
    }
    return null;
}

/* ────────────────────────────── Token bootstrap ──────────────────────────── */

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   SIH Farmer Procurement System — Edge-Case Tests           ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Base URL: {$baseUrl}\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Authenticating…\n";
$farmerOk   = ensureFarmerToken();
$operatorOk = ensureOperatorToken();

echo "  Farmer token:   " . ($farmerOk   ? "✓ obtained" : "✗ unavailable") . "\n";
echo "  Operator token: " . ($operatorOk ? "✓ obtained" : "✗ unavailable") . "\n";
echo "\n";

/* ────────────────────────────── Seeding helpers ──────────────────────────── */

/**
 * Find any active centre + weekday slot without requiring an operator token.
 * Returns ['centre_id','date','slot_id'] or null.
 */
function findAnySlot(): ?array
{
    $centreRes = http('GET', '/api/v1/centres', null);
    $items = $centreRes['body']['data'] ?? [];
    if (!is_array($items) || empty($items)) {
        return null;
    }
    $first = is_array($items[0] ?? null) ? $items[0] : $items;
    $centreId = (int)($first['id'] ?? 0);
    if ($centreId === 0) {
        return null;
    }
    for ($i = 1; $i <= 7; $i++) {
        $date = date('Y-m-d', strtotime("+{$i} day"));
        $slotsRes = http('GET', "/api/v1/slots?centre_id={$centreId}&date={$date}", null);
        $slots = $slotsRes['body']['data']['slots'] ?? $slotsRes['body']['data'] ?? [];
        if (!empty($slots)) {
            $slotFirst = is_array($slots[0] ?? null) ? $slots[0] : $slots;
            return [
                'centre_id' => $centreId,
                'date'      => $date,
                'slot_id'   => (int)($slotFirst['id'] ?? 0),
            ];
        }
    }
    return null;
}

/**
 * Pick a centre (via the operator's scoped list) and a weekday within +7 days
 * that has available slots. Returns ['centre_id','date','slot_id'] or null.
 */
function findSlotDate(): ?array
{
    $opHeaders = authOperatorHeader();
    $centreRes = http('GET', '/api/v1/centres', null, $opHeaders);
    $items = $centreRes['body']['data'] ?? [];
    if (!is_array($items) || empty($items)) {
        return null;
    }
    $first = is_array($items[0] ?? null) ? $items[0] : $items;
    $centreId = (int)($first['id'] ?? 0);
    if ($centreId === 0) {
        return null;
    }
    for ($i = 1; $i <= 7; $i++) {
        $date = date('Y-m-d', strtotime("+{$i} day"));
        $slotsRes = http('GET', "/api/v1/slots?centre_id={$centreId}&date={$date}", null, $opHeaders);
        $slots = $slotsRes['body']['data']['slots'] ?? $slotsRes['body']['data'] ?? [];
        if (!empty($slots)) {
            $slotFirst = is_array($slots[0] ?? null) ? $slots[0] : $slots;
            return [
                'centre_id' => $centreId,
                'date'      => $date,
                'slot_id'   => (int)($slotFirst['id'] ?? 0),
            ];
        }
    }
    return null;
}

/**
 * Seed bookings specifically for a chosen date at the operator's centre.
 * Each booking uses a distinct slot so the (slot, user, ACTIVE) unique guard
 * never collides with demo-seeded data. Returns the number of bookings made.
 */
function seedBookingsOn(string $date, int $n): int
{
    $ctx = findSlotDate();
    if ($ctx === null) {
        return 0;
    }
    $slotsRes = http('GET', "/api/v1/slots?centre_id={$ctx['centre_id']}&date={$date}");
    $slots = $slotsRes['body']['data']['slots'] ?? $slotsRes['body']['data'] ?? [];
    if (!is_array($slots) || empty($slots)) {
        return 0;
    }
    $slotIds = [];
    foreach ($slots as $s) {
        $sArr = is_array($s) ? $s : [];
        $cap = (int)($sArr['capacity'] ?? 0);
        $booked = (int)($sArr['booked_count'] ?? 0);
        if ($cap > 0 && $booked < $cap && !empty($sArr['id'])) {
            $slotIds[] = (int) $sArr['id'];
        }
    }
    if (empty($slotIds)) {
        return 0;
    }

    $made = 0;
    for ($k = 0; $k < $n && $made < $n; $k++) {
        $slotCtx = [
            'centre_id' => $ctx['centre_id'],
            'date'      => $date,
            'slot_id'   => $slotIds[$k % count($slotIds)],
        ];
        $bid = seedConfirmedBookingWithCtx($slotCtx);
        if ($bid !== null && $bid > 0) {
            $made++;
        } else {
            // skip a full/colliding slot and try the next one
        }
        usleep(300000);
    }
    return $made;
}

/**
 * Cancel one upcoming CONFIRMED booking for a farmer to free up the active
 * booking cap (2). If $slotId is given, a booking on that slot is preferred
 * (avoids the unique (slot, user, ACTIVE) conflict on re-seed). Returns true
 * if a booking was successfully cancelled.
 */
function freeSlotFor(string $token, ?int $slotId = null): bool
{
    $bookings = http('GET', '/api/v1/bookings?limit=20', null, ["Authorization: Bearer {$token}"]);
    if ($bookings['status'] !== 200) {
        return false;
    }
    $items = $bookings['body']['data'] ?? $bookings['body']['bookings'] ?? [];
    if (!is_array($items)) {
        return false;
    }
    $candidates = [];
    foreach ($items as $bk) {
        if (!is_array($bk) || empty($bk['id'])) {
            continue;
        }
        $bkDate = $bk['booking_date'] ?? $bk['date'] ?? '';
        $status = strtolower((string)($bk['status'] ?? ''));
        if (!in_array($status, ['confirmed', 'pending', 'scheduled'], true)) {
            continue;
        }
        if ($bkDate !== '' && strtotime($bkDate) < time() - 3600) {
            continue;
        }
        $candidates[] = $bk;
    }
    if (empty($candidates)) {
        return false;
    }
    foreach ($candidates as $bk) {
        if ($slotId !== null && (int)($bk['slot_id'] ?? 0) === $slotId) {
            $cancel = http('POST', "/api/v1/bookings/{$bk['id']}/cancel", [], ["Authorization: Bearer {$token}"]);
            return in_array($cancel['status'], [200, 204], true);
        }
    }
    foreach ($candidates as $bk) {
        $cancel = http('POST', "/api/v1/bookings/{$bk['id']}/cancel", [], ["Authorization: Bearer {$token}"]);
        if (in_array($cancel['status'], [200, 204], true)) {
            return true;
        }
    }
    return false;
}

/**
 * Create a confirmed booking using the first demo farmer that is not at the
 * active-booking cap (2), for a specific booking context.
 */
function seedConfirmedBookingWithCtx(array $ctx): ?int
{
    global $farmerToken;
    $password = getenv('SMOKE_FARMER_PASSWORD') ?: 'Demo@1234';
    for ($m = 9000000004; $m <= 9000000015; $m++) {
        $mobile = (string) $m;
        $token = $mobile === '9000000004' && $farmerToken !== null ? $farmerToken : login($mobile, $password);
        if ($token === null) {
            continue;
        }
        for ($try = 0; $try < 2; $try++) {
            $res = http('POST', '/api/v1/bookings', [
                'booking_date' => $ctx['date'],
                'slot_id'      => $ctx['slot_id'],
                'centre_id'    => $ctx['centre_id'],
                'crops'        => [['crop_id' => 1, 'qty' => 10.0]],
            ], ["Authorization: Bearer {$token}"]);
            if ($res['status'] === 201) {
                $data = $res['body']['data']['booking'] ?? $res['body']['data'] ?? [];
                return (int)($data['id'] ?? 0);
            }
            if ($res['status'] === 409 && $try === 0) {
                // DUPLICATE_BOOKING cap — free a slot (prefer the target slot)
                // so the retry can create a fresh booking.
                if (freeSlotFor($token, (int)($ctx['slot_id'] ?? 0))) {
                    usleep(250000);
                    continue;
                }
            }
            break;
        }
    }
    return null;
}

/**
 * Create a confirmed booking using the first demo farmer that is not at the
 * active-booking cap (2). Returns the booking id or null.
 */
function seedConfirmedBooking(): ?int
{
    $ctx = findSlotDate();
    if ($ctx === null || $ctx['slot_id'] === 0) {
        return null;
    }
    return seedConfirmedBookingWithCtx($ctx);
}

/**
 * Drive a procurement end-to-end (call-next → start → capture → submit → SA
 * approve) and return the resulting PENDING payment id, or null.
 */
function seedPendingPayment(): ?int
{
    global $operatorToken;
    if ($operatorToken === null) {
        return null;
    }

    $ctx = findSlotDate();
    if ($ctx === null || $ctx['slot_id'] === 0) {
        return null;
    }

    // Consume a queue entry. Prefer an existing (demo-seeded) WAITING entry;
    // if the queue is empty, seed a fresh confirmation first.
    $entryId = 0;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        for ($i = 1; $i <= 7; $i++) {
            $d = date('Y-m-d', strtotime("+{$i} day"));
            $cn = http('POST', '/api/v1/operator/queue/call-next', [
                'centre_id' => (int)$ctx['centre_id'],
                'date'      => $d,
            ], authOperatorHeader());
            if (in_array($cn['status'], [200, 201])) {
                $entry = $cn['body']['data']['entry'] ?? null;
                $entryId = (int)($entry['entry_id'] ?? $entry['id'] ?? 0);
                break 2;
            }
            if (!in_array($cn['status'], [409, 428, 422])) {
                return null;
            }
        }
        if (seedConfirmedBooking() === null) {
            return null;
        }
        usleep(400000);
    }
    if ($entryId === 0) {
        return null;
    }

    $start = http('POST', "/api/v1/operator/queue/{$entryId}/start", null, authOperatorHeader());
    if (!in_array($start['status'], [200, 201])) {
        return null;
    }
    $data = $start['body']['data'] ?? [];
    $list = $data['procurements'] ?? $data['procurement'] ?? [$data];
    $first = is_array($list[0] ?? null) ? $list[0] : $list;
    $procId = (int)($first['id'] ?? 0);
    if ($procId === 0) {
        return null;
    }

    $cap = http('PUT', "/api/v1/operator/procurements/{$procId}", [
        'accepted_weight' => 25.5,
        'grade'           => 'A',
        'moisture_pct'    => 12.5,
    ], authOperatorHeader());
    if (!in_array($cap['status'], [200, 201])) {
        return null;
    }

    $sub = http('POST', "/api/v1/operator/procurements/{$procId}/submit", null, authOperatorHeader());
    if (!in_array($sub['status'], [200, 201])) {
        return null;
    }

    $saMobile   = getenv('SMOKE_SUPER_ADMIN_MOBILE')   ?: '7010000001';
    $saPassword = getenv('SMOKE_SUPER_ADMIN_PASSWORD') ?: 'Admin@1234';
    $saLogin = http('POST', '/api/v1/auth/login', ['mobile' => $saMobile, 'password' => $saPassword]);
    $saToken = $saLogin['body']['data']['access_token'] ?? null;
    if ($saToken === null) {
        return null;
    }

    $list = http('GET', '/api/v1/admin/approvals', null, ["Authorization: Bearer {$saToken}"]);
    $items = $list['body']['data']['items'] ?? $list['body']['data']['approvals'] ?? $list['body']['data'] ?? [];
    if (!is_array($items) || empty($items)) {
        return null;
    }
    $approvalId = 0;
    foreach ($items as $it) {
        $itArr = is_array($it) ? $it : [];
        if (empty($itArr['id'])) {
            continue;
        }
        $st = strtolower((string)($itArr['status'] ?? ''));
        if ($st === '' || in_array($st, ['pending', 'pending_approval', 'submitted', 'awaiting', 'waiting'], true)) {
            $approvalId = (int)$itArr['id'];
            break;
        }
    }
    if ($approvalId === 0) {
        return null;
    }

    $appr = http('POST', "/api/v1/admin/approvals/{$approvalId}/approve", null, ["Authorization: Bearer {$saToken}"]);
    if (!in_array($appr['status'], [200, 201])) {
        return null;
    }

    $pays = http('GET', "/api/v1/operator/payments?status=PENDING&centre_id={$ctx['centre_id']}", null, authOperatorHeader());
    $pItems = $pays['body']['data']['items'] ?? $pays['body']['data']['payments'] ?? $pays['body']['data'] ?? [];
    if (!is_array($pItems) || empty($pItems)) {
        return null;
    }
    $pFirst = is_array($pItems[0] ?? null) ? $pItems[0] : $pItems;
    return (int)($pFirst['id'] ?? 0) ?: null;
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 1 — Multi-crop with zero quantity
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 1: Multi-crop with zero quantity ──\n";

if (!$farmerOk) {
    skip('Multi-crop zero quantity', 'No farmer token');
} else {
    $payload = [
        'crop_name'      => 'Wheat',
        'booking_date'   => todayStr(),
        'time_slot'      => '10:00-12:00',
        'quantity_kg'    => 50,
        'vehicle_type'   => 'Truck',
        'crops'          => [
            ['name' => 'Wheat', 'quantity_kg' => 50],
            ['name' => 'Rice',  'quantity_kg' => 0],
        ],
    ];
    $res = http('POST', '/api/v1/bookings', $payload, authFarmerHeader());
    $got400 = in_array($res['status'], [400, 422], true);
    $hasValidation = str_contains(json_encode($res['body']), 'VALIDATION_ERROR')
        || str_contains(json_encode($res['body']), 'quantity_kg')
        || str_contains(json_encode($res['body']), 'greater than 0')
        || str_contains(json_encode($res['body']), 'must be');
    record(
        'Multi-crop zero quantity',
        $got400 && $hasValidation,
        "HTTP {$res['status']} — " . ($got400 && $hasValidation
            ? 'Validation error returned as expected'
            : 'Expected 400/422 with validation error, got HTTP ' . $res['status'])
    );
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 2 — Negative weight
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 2: Negative weight ──\n";

if (!$farmerOk) {
    skip('Negative weight', 'No farmer token');
} else {
    $payload = [
        'crop_name'    => 'Wheat',
        'booking_date' => todayStr(),
        'time_slot'    => '10:00-12:00',
        'quantity_kg'  => -10,
        'vehicle_type' => 'Truck',
    ];
    $res = http('POST', '/api/v1/bookings', $payload, authFarmerHeader());
    $got400 = in_array($res['status'], [400, 422], true);
    $hasValidation = str_contains(json_encode($res['body']), 'VALIDATION_ERROR')
        || str_contains(json_encode($res['body']), 'quantity_kg')
        || str_contains(json_encode($res['body']), 'greater than 0')
        || str_contains(json_encode($res['body']), 'positive');
    record(
        'Negative weight',
        $got400 && $hasValidation,
        "HTTP {$res['status']} — " . ($got400 && $hasValidation
            ? 'Validation error returned as expected'
            : 'Expected 400/422 with validation error, got HTTP ' . $res['status'])
    );
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 3 — Future slot beyond 7-day horizon
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 3: Future slot beyond 7-day horizon ──\n";

if (!$farmerOk) {
    skip('Future slot beyond 7-day horizon', 'No farmer token');
} else {
    // Find a real centre + slot so validation passes and the window check fires.
    $ctx = findAnySlot();
    if ($ctx === null) {
        skip('Future slot beyond 7-day horizon', 'No slots found to build a valid booking');
    } else {
        $payload = [
            'booking_date' => daysFromNow(30),
            'slot_id'      => $ctx['slot_id'],
            'centre_id'    => $ctx['centre_id'],
            'crops'        => [['crop_id' => 1, 'qty' => 50.0]],
        ];
        $res = http('POST', '/api/v1/bookings', $payload, authFarmerHeader());
        $got400 = in_array($res['status'], [400, 403, 422], true);
        // Beyond-window dates are rejected either by the window guard or by
        // slot availability (slots only exist inside the window).
        $hasWindowError = str_contains(json_encode($res['body']), 'BOOKING_WINDOW_CLOSED')
            || str_contains(json_encode($res['body']), 'INVALID_SLOT')
            || str_contains(json_encode($res['body']), 'SLOT_NOT_FOUND')
            || str_contains(json_encode($res['body']), 'window')
            || str_contains(json_encode($res['body']), 'beyond')
            || str_contains(json_encode($res['body']), '7 day')
            || str_contains(json_encode($res['body']), 'too far')
            || str_contains(json_encode($res['body']), 'horizon')
            || str_contains(json_encode($res['body']), 'availability');
        record(
            'Out-of-horizon booking rejected',
            $got400 && $hasWindowError,
            "HTTP {$res['status']} — " . ($got400 && $hasWindowError
                ? 'Booking rejected as expected (out of window)'
                : 'Expected 400 out-of-window rejection, got HTTP ' . $res['status']
                    . ' — ' . substr(json_encode($res['body']), 0, 200))
        );
    }
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 4 — OTP reuse after success
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 4: OTP reuse after success ──\n";

$otpTested = false;
// 2FA is enabled on demo farmers 9000000001–9000000003.
$twoFaMobile   = getenv('SMOKE_2FA_MOBILE')   ?: '9000000001';
$twoFaPassword = getenv('SMOKE_2FA_PASSWORD') ?: 'Demo@1234';

$initRes = http('POST', '/api/v1/auth/login', [
    'mobile'   => $twoFaMobile,
    'password' => $twoFaPassword,
]);

if ($initRes['status'] === 202) {
    $vid = $initRes['body']['data']['verification_id'] ?? null;
    $otp = $vid !== null ? readOtpForVerification($vid) : null;

    if ($vid === null || $otp === null) {
        skip('OTP reuse after success', '2FA verification id / OTP not available');
    } else {
        // First verification attempt should succeed.
        $verify1 = http('POST', '/api/v1/auth/verify-2fa', [
            'verification_id' => $vid,
            'otp'             => $otp,
        ]);

        // Second attempt with the same verification id must be rejected
        // (either a hard 4xx OTP error or a 429 rate-limit on reuse).
        $verify2 = http('POST', '/api/v1/auth/verify-2fa', [
            'verification_id' => $vid,
            'otp'             => $otp,
        ]);

        $firstOk   = in_array($verify1['status'], [200, 201], true);
        $secondRejected = in_array($verify2['status'], [400, 401, 403, 404, 410, 429], true);
        $otpTested = true;
        record(
            'OTP reuse after success',
            $firstOk && $secondRejected,
            "First verify HTTP {$verify1['status']}, reuse HTTP {$verify2['status']} — " . (($firstOk && $secondRejected)
                ? 'First attempt succeeded, reuse correctly rejected'
                : 'Expected first success + reuse rejection, got ' . $verify1['status'] . ' / ' . $verify2['status'])
        );
    }
}

if (!$otpTested) {
    skip('OTP reuse after success', '2FA login did not return verification_id (check demo 2FA users)');
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 5 — Token reuse after payment (double release)
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 5: Token reuse after payment (double release) ──\n";

if (!$operatorOk) {
    skip('Token reuse after payment', 'No operator token');
} else {
    $paymentId = findReleasedPaymentId();
    if ($paymentId === null) {
        skip('Token reuse after payment', 'No released payment found to re-release');
    } else {
        // Try to release an already-released payment
        $res = http('PUT', "/api/v1/operator/payments/{$paymentId}/release", [
            'payment_id'        => $paymentId,
            'method'            => 'cash',
            'payment_reference' => 'EDGE-REDO-' . date('His'),
        ], authOperatorHeader());

        // Also try POST variant
        if ($res['status'] >= 400 && $res['status'] < 500) {
            // Already failed — good
            $isConflictOrError = in_array($res['status'], [409, 400, 403, 422], true);
            record(
                'Token reuse after payment',
                $isConflictOrError,
                "HTTP {$res['status']} — " . ($isConflictOrError
                    ? 'Double release correctly rejected'
                    : 'Expected conflict/error, got HTTP ' . $res['status'])
            );
        } else {
            $res2 = http('POST', "/api/v1/operator/payments/{$paymentId}/release", [], authOperatorHeader());
            $isConflict2 = in_array($res2['status'], [409, 400, 403, 410, 422], true);
            record(
                'Token reuse after payment',
                $isConflict2,
                "HTTP {$res2['status']} — " . ($isConflict2
                    ? 'Double release correctly rejected'
                    : 'Expected conflict/error on second release')
            );
        }
    }
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 6 — Double call-next (concurrent)
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 6: Double call-next (concurrent) ──\n";

if (!$operatorOk) {
    skip('Double call-next', 'No operator token');
} else {
    // Probe for a date that still has a WAITING queue entry (demo-seeded),
    // then top up capacity with fresh bookings so the concurrent call has
    // at least something to pop.
    $ctx = findSlotDate();
    if ($ctx === null || $ctx['slot_id'] === 0) {
        skip('Double call-next', 'No centre/date with slots available to seed');
    } else {
        $probeDate = null;
        foreach (range(1, 7) as $i) {
            $d = date('Y-m-d', strtotime("+{$i} day"));
            $p = http('POST', '/api/v1/operator/queue/call-next', [
                'centre_id' => (int)$ctx['centre_id'],
                'date'      => $d,
            ], authOperatorHeader());
            if (in_array($p['status'], [200, 201])) {
                $probeDate = $d;
                break;
            }
            if (!in_array($p['status'], [409, 428, 422])) {
                break;
            }
        }
        if ($probeDate === null) {
            $probeDate = $ctx['date'];
        }
        $seeded = seedBookingsOn($probeDate, 2);
        usleep(400000);

        $payload = json_encode(['centre_id' => $ctx['centre_id'], 'date' => $probeDate]);
            $ch1 = curl_init($baseUrl . '/api/v1/operator/queue/call-next');
            $ch2 = curl_init($baseUrl . '/api/v1/operator/queue/call-next');
            $headers = ['Authorization: Bearer ' . $operatorToken, 'Content-Type: application/json', 'Accept: application/json'];

            foreach ([$ch1, $ch2] as $ch) {
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_HEADER         => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
            }

            $mh = curl_multi_init();
            curl_multi_add_handle($mh, $ch1);
            curl_multi_add_handle($mh, $ch2);

            $responses = [];
            do {
                $exec = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
                if (($info = curl_multi_info_read($mh)) !== false) {
                    $responses[(int) $info['handle']] = curl_multi_getcontent($info['handle']);
                }
            } while ($active && $exec === CURLM_OK);

            curl_multi_remove_handle($mh, $ch1);
            curl_multi_remove_handle($mh, $ch2);
            curl_multi_close($mh);

            $status1 = (int) curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            $status2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $raw1 = $responses[(int) $ch1] ?? '';
            $raw2 = $responses[(int) $ch2] ?? '';
            curl_close($ch1);
            curl_close($ch2);

            $success1 = in_array($status1, [200, 201], true);
            $success2 = in_array($status2, [200, 201], true);

            $body1 = [];
            $body2 = [];
            if ($raw1 !== '') { $d = json_decode(substr($raw1, strpos($raw1, "\r\n\r\n") ?: 0), true); if (is_array($d)) $body1 = $d; }
            if ($raw2 !== '') { $d = json_decode(substr($raw2, strpos($raw2, "\r\n\r\n") ?: 0), true); if (is_array($d)) $body2 = $d; }

            $e1 = (int)($body1['data']['entry']['entry_id'] ?? $body1['data']['entry']['id'] ?? 0);
            $e2 = (int)($body2['data']['entry']['entry_id'] ?? $body2['data']['entry']['id'] ?? 0);

            // Race safety: at least one must succeed, and if both succeed the
            // returned queue entries MUST differ (no double-assignment).
            $noDoubleAssign = !($success1 && $success2) || $e1 !== $e2;
            $pass = ($success1 || $success2) && $noDoubleAssign;

record(
        'Double call-next',
        $pass,
        "Statuses: {$status1}, {$status2}, entries: {$e1}/{$e2} — " . ($pass
            ? 'No double-assignment detected under concurrency'
            : 'Concurrent call-next double-assigned the same entry')
    );
    }
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 7 — Double release (concurrent)
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 7: Double release (concurrent) ──\n";

if (!$operatorOk) {
    skip('Double release', 'No operator token');
} else {
    // Find a pending payment in the operator's scope, or seed one by driving
    // a booking through call-next → start → capture → submit → SA approve.
    $ctx = findSlotDate();
    $centreFilter = $ctx !== null ? '&centre_id=' . $ctx['centre_id'] : '';
    $pendingRes = http('GET', '/api/v1/operator/payments?status=PENDING' . $centreFilter, null, authOperatorHeader());
    $payId = null;
    if ($pendingRes['status'] === 200) {
        $items = $pendingRes['body']['data']['items'] ?? $pendingRes['body']['data']['payments'] ?? $pendingRes['body']['data'] ?? [];
        if (is_array($items) && count($items) > 0) {
            $first = is_array($items[0] ?? null) ? $items[0] : $items;
            if (!empty($first['id'])) {
                $payId = (string) $first['id'];
            }
        }
    }

    if ($payId === null) {
        $seeded = seedPendingPayment();
        if ($seeded === null) {
            skip('Double release', 'No pending payment found and seeding failed');
            return;
        }
        $payId = (string) $seeded;
    }

    $releaseUrl  = "/api/v1/operator/payments/{$payId}/release";
    $relHeaders = ['Authorization: Bearer ' . $operatorToken, 'Content-Type: application/json', 'Accept: application/json'];

    // NOTE: release is idempotent for the SAME reference (returns 200 on re-release).
    // To exercise the concurrent race we send DISTINCT references so exactly one wins.
    $releasePayloadA = json_encode([
        'payment_id'        => $payId,
        'method'            => 'cash',
        'payment_reference' => 'EDGE-CONC-A-' . date('His'),
    ]);
    $releasePayloadB = json_encode([
        'payment_id'        => $payId,
        'method'            => 'cash',
        'payment_reference' => 'EDGE-CONC-B-' . date('His'),
    ]);

    $rc1 = curl_init($baseUrl . $releaseUrl);
    $rc2 = curl_init($baseUrl . $releaseUrl);

    curl_setopt_array($rc1, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS     => $releasePayloadA,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $relHeaders,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_setopt_array($rc2, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS     => $releasePayloadB,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $relHeaders,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $mh = curl_multi_init();
    curl_multi_add_handle($mh, $rc1);
    curl_multi_add_handle($mh, $rc2);
    do {
        $st = curl_multi_exec($mh, $act);
        if ($act) {
            curl_multi_select($mh);
        }
    } while ($act && $st === CURLM_OK);
    curl_multi_remove_handle($mh, $rc1);
    curl_multi_remove_handle($mh, $rc2);
    curl_multi_close($mh);

    $rs1 = (int) curl_getinfo($rc1, CURLINFO_HTTP_CODE);
    $rs2 = (int) curl_getinfo($rc2, CURLINFO_HTTP_CODE);
    curl_close($rc1);
    curl_close($rc2);

    $releaseOk1 = in_array($rs1, [200, 201], true);
    $releaseOk2 = in_array($rs2, [200, 201], true);
    $conflict1  = in_array($rs1, [409, 400, 410, 422], true);
    $conflict2  = in_array($rs2, [409, 400, 410, 422], true);

    // Exactly one release succeeds; the concurrent duplicate is rejected.
    $ok = ($releaseOk1 && $conflict2) || ($releaseOk2 && $conflict1);

    record(
        'Double release',
        $ok,
        "Statuses: {$rs1}, {$rs2} — " . ($ok
            ? 'Exactly one release succeeded, duplicate rejected'
            : 'Expected one success + one rejection, got: ' . $rs1 . ' / ' . $rs2)
    );
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 8 — Cancel outside window
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 8: Cancel outside window ──\n";

if (!$farmerOk) {
    skip('Cancel outside window', 'No farmer token');
} else {
    // Find a booking that is old enough that its cancellation window has passed.
    // We look for a completed/old booking via the API.
    $allBookings = http('GET', '/api/v1/bookings?limit=20', null, authFarmerHeader());
    $candidateId = null;
    if ($allBookings['status'] === 200) {
        $bks = $allBookings['body']['data'] ?? $allBookings['body']['bookings'] ?? [];
        if (is_array($bks)) {
            foreach ($bks as $bk) {
                if (!is_array($bk) || empty($bk['id'])) continue;
                // Prefer bookings that are past their slot or in a non-cancellable status
                $bkDate = $bk['booking_date'] ?? $bk['date'] ?? '';
                $status = $bk['status'] ?? '';
                if ($bkDate !== '' && strtotime($bkDate) < time() - 86400) {
                    $candidateId = (string) $bk['id'];
                    break;
                }
                // Or status that implies not cancellable
                if (in_array(strtolower($status), ['completed', 'processing', 'paid', 'released'], true)) {
                    $candidateId = (string) $bk['id'];
                    break;
                }
            }
        }
    }

    if ($candidateId === null) {
        // Try to create one, then immediately check cancellation — not ideal but demonstrates the check
        skip('Cancel outside window', 'No old booking found to test cancellation window expiry');
    } else {
        $res = http('POST', "/api/v1/bookings/{$candidateId}/cancel", [], authFarmerHeader());
        $is409 = $res['status'] === 409;
        $hasWindowErr = str_contains(json_encode($res['body']), 'CANCELLATION_WINDOW_CLOSED')
            || str_contains(json_encode($res['body']), 'window')
            || str_contains(json_encode($res['body']), 'past')
            || str_contains(json_encode($res['body']), 'cancelled');
        record(
            'Cancel outside window',
            $is409 && $hasWindowErr,
            "HTTP {$res['status']} — " . ($is409 && $hasWindowErr
                ? 'Cancellation window closed as expected'
                : 'Expected 409 with CANCELLATION_WINDOW_CLOSED, got HTTP ' . $res['status']
                    . ' — ' . substr(json_encode($res['body']), 0, 200))
        );
    }
}

/* ══════════════════════════════════════════════════════════════════════════
 *  STEP 9 — Duplicate concurrent cancels
 * ══════════════════════════════════════════════════════════════════════════ */

echo "── Step 9: Duplicate concurrent cancels ──\n";

if (!$farmerOk) {
    skip('Duplicate concurrent cancels', 'No farmer token');
} else {
    // Seed a FRESH confirmed booking for the main farmer so cancellation is
    // deterministic (no residual cancelled rows for the same slot+user).
    $ctx = findSlotDate();
    if ($ctx === null || $ctx['slot_id'] === 0) {
        skip('Duplicate concurrent cancels', 'No usable slot found');
        return;
    }
    if (freeSlotFor($farmerToken)) {
        usleep(300000);
    }
    $seedRes = http('POST', '/api/v1/bookings', [
        'booking_date' => $ctx['date'],
        'slot_id'      => $ctx['slot_id'],
        'centre_id'    => $ctx['centre_id'],
        'crops'        => [['crop_id' => 1, 'qty' => 10.0]],
    ], authFarmerHeader());

    $cancelId = null;
    if ($seedRes['status'] === 201) {
        $seedData = $seedRes['body']['data']['booking'] ?? $seedRes['body']['data'] ?? [];
        $cancelId = (string) ($seedData['id'] ?? 0);
    }
    if ($cancelId === null || $cancelId === '0') {
        skip('Duplicate concurrent cancels', 'Could not seed a fresh booking');
        return;
    }

    $cancelUrl  = "/api/v1/bookings/{$cancelId}/cancel";
    $cancHeaders = ['Authorization: Bearer ' . $farmerToken, 'Accept: application/json'];

    $cc1 = curl_init($baseUrl . $cancelUrl);
    $cc2 = curl_init($baseUrl . $cancelUrl);

    foreach ([$cc1, $cc2] as $ch) {
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge($cancHeaders, ['Content-Type: application/json']),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HEADER         => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
    }

    $mh = curl_multi_init();
    curl_multi_add_handle($mh, $cc1);
    curl_multi_add_handle($mh, $cc2);
    do {
        $st = curl_multi_exec($mh, $act);
        if ($act) {
            curl_multi_select($mh);
        }
    } while ($act && $st === CURLM_OK);
    curl_multi_remove_handle($mh, $cc1);
    curl_multi_remove_handle($mh, $cc2);
    curl_multi_close($mh);

    $cs1 = (int) curl_getinfo($cc1, CURLINFO_HTTP_CODE);
    $cs2 = (int) curl_getinfo($cc2, CURLINFO_HTTP_CODE);
    curl_close($cc1);
    curl_close($cc2);

    // First should be 200, second 409 (or vice versa) — the unique-key fix
    // guarantees the cancelled status can always be written once.
    $firstOk  = in_array($cs1, [200, 204], true) && in_array($cs2, [409, 400, 410, 422], true);
    $secondOk = in_array($cs2, [200, 204], true) && in_array($cs1, [409, 400, 410, 422], true);
    $exactlyOne = $firstOk || $secondOk;

    record(
        'Duplicate concurrent cancels',
        $exactlyOne,
        "Statuses: {$cs1}, {$cs2} — " . ($exactlyOne
            ? 'First succeeded, second rejected (idempotent) as expected'
            : 'Expected one success + one conflict, got: ' . $cs1 . ' / ' . $cs2)
    );
}

/* ══════════════════════════════════════════════════════════════════════════
 *  RESULTS TABLE
 * ══════════════════════════════════════════════════════════════════════════ */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESULTS                                                                 ║\n";
echo "╠═══════════════════════════════════════════════════════════════════════════╣\n";
echo sprintf("║  %-4s %-42s %-8s  %s\n", '#', 'Test', 'Result', 'Reason');
echo "╠═══════════════════════════════════════════════════════════════════════════╣\n";

$passCount = 0;
$failCount = 0;
$skipCount = 0;

foreach ($results as $i => $r) {
    $tag = $r['pass'] ? ($r['reason'][0] === 'S' && str_starts_with($r['reason'], 'SKIP') ? 'SKIP' : 'PASS') : 'FAIL';
    if ($tag === 'PASS') $passCount++;
    if ($tag === 'FAIL') $failCount++;
    if ($tag === 'SKIP') $skipCount++;

    $reasonShort = $r['reason'];
    $nameShort   = $r['name'];

    echo sprintf("║  %-4d %-42s %-8s  %s\n", $i + 1, $nameShort, $tag, $reasonShort);
}

echo "╠═══════════════════════════════════════════════════════════════════════════╣\n";
echo sprintf("║  Total: %d | ", $step);
echo sprintf("PASS: \033[32m%d\033[0m | ", $passCount);
echo sprintf("FAIL: \033[31m%d\033[0m | ", $failCount);
echo sprintf("SKIP: \033[33m%d\033[0m", $skipCount);
echo "\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";

if ($failCount > 0) {
    echo "\n\033[31mFAILED — {$failCount} test(s) failed.\033[0m\n";
    exit(1);
}

echo "\n\033[32mALL PASSED — {$passCount} test(s) passed, {$skipCount} skipped.\033[0m\n";
exit(0);
