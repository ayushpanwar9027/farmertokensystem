<?php

declare(strict_types=1);

// Phase 13 E2E verification via raw HTTP against php -S 127.0.0.1:8090.
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

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
    $headers = ['Content-Type' => $contentType ?? 'application/json'];
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

function jdec($body): array
{
    $d = json_decode((string) $body, true);
    return is_array($d) ? $d : [];
}

function bodyContains(string $body, string $needle): bool
{
    return str_contains((string) $body, $needle);
}

function setSetting(string $key, $val): void
{
    (new \App\Services\SettingService())->set($key, $val);
}

echo "=== Phase 13 baseline setup ===" . PHP_EOL;

$suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$c1Code = 'P13A' . $suffix;
$c2Code = 'P13B' . $suffix;

function ensureCentre(string $code, string $name, string $suffix, int $capacity): int
{
    $row = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$code]);
    if ($row === null) {
        return (int) Database::insert('procurement_centres', [
            'name' => $name . ' ' . $suffix,
            'code' => $code,
            'district_id' => 1,
            'address' => 'Phase 13 Proc ' . $code,
            'working_hours_start' => '06:00:00',
            'working_hours_end' => '22:00:00',
            'daily_capacity' => $capacity,
            'slot_duration_minutes' => 30,
            'status' => 'ACTIVE',
        ]);
    }
    $centreId = (int) $row['id'];
    Database::update('procurement_centres', ['status' => 'ACTIVE', 'deleted_at' => null], 'id = ?', [$centreId]);
    return $centreId;
}

$c1Id = ensureCentre($c1Code, 'P13 Proc Centre', $suffix, 100);
$c2Id = ensureCentre($c2Code, 'P13 Other Centre', $suffix, 100);

foreach ([$c1Id, $c2Id] as $centreId) {
    Database::getConnection()->prepare("DELETE p FROM payments p INNER JOIN procurements pr ON pr.id = p.procurement_id INNER JOIN bookings b ON b.id = pr.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE p FROM procurements p INNER JOIN bookings b ON b.id = p.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM queue_entries WHERE centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE t FROM tokens t INNER JOIN bookings b ON b.id = t.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE bc FROM booking_crops bc INNER JOIN bookings b ON b.id = bc.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM bookings WHERE centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM slots WHERE centre_id = ?")->execute([$centreId]);
}

$wheat = Database::selectOne("SELECT id, name FROM crops WHERE code = 'WHEAT' AND deleted_at IS NULL LIMIT 1");
if ($wheat === null) {
    $wheatId = (int) Database::insert('crops', ['code' => 'WHEAT', 'name' => 'Wheat', 'is_active' => 1, 'is_default' => 1]);
    $wheatName = 'Wheat';
} else {
    $wheatId = (int) $wheat['id'];
    $wheatName = (string) $wheat['name'];
}

$baseRateRow = Database::selectOne(
    "SELECT rate_per_kg FROM crop_rates WHERE crop_id = ? AND centre_id IS NULL AND is_active = 1 ORDER BY effective_from DESC LIMIT 1",
    [$wheatId]
);
$baseRate = $baseRateRow !== null ? (float) $baseRateRow['rate_per_kg'] : 22.50;

Database::getConnection()->prepare("DELETE FROM crop_rates WHERE crop_id = ? AND centre_id = ?")->execute([$wheatId, $c1Id]);

$slot1 = (int) Database::insert('slots', [
    'centre_id' => $c1Id,
    'date' => $tomorrow,
    'start_time' => '10:00:00',
    'end_time' => '10:30:00',
    'capacity' => 10,
    'booked_count' => 0,
    'status' => 'ACTIVE',
    'created_by' => 1,
]);
$slot2 = (int) Database::insert('slots', [
    'centre_id' => $c2Id,
    'date' => $tomorrow,
    'start_time' => '11:00:00',
    'end_time' => '11:30:00',
    'capacity' => 10,
    'booked_count' => 0,
    'status' => 'ACTIVE',
    'created_by' => 1,
]);
$slot3 = (int) Database::insert('slots', [
    'centre_id' => $c1Id,
    'date' => $tomorrow,
    'start_time' => '12:00:00',
    'end_time' => '12:30:00',
    'capacity' => 10,
    'booked_count' => 0,
    'status' => 'ACTIVE',
    'created_by' => 1,
]);

function createTestUser(string $username, string $name, int $roleId, string $mobile, string $password): int
{
    $existing = Database::selectOne("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing !== null) {
        $userId = (int) $existing['id'];
        Database::getConnection()->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$userId]);
        Database::getConnection()->prepare("DELETE FROM notification_logs WHERE user_id = ?")->execute([$userId]);
        Database::getConnection()->prepare("DELETE FROM centre_staff WHERE user_id = ?")->execute([$userId]);
        Database::getConnection()->prepare("DELETE FROM farmers WHERE user_id = ?")->execute([$userId]);
        Database::getConnection()->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    }
    return (int) Database::insert('users', [
        'name' => $name,
        'mobile' => $mobile,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role_id' => $roleId,
        'status' => 'ACTIVE',
        'verification_status' => 'APPROVED',
        'mobile_verified_at' => date('Y-m-d H:i:s'),
        'password_set_at' => date('Y-m-d H:i:s'),
        'is_super_admin' => 0,
    ]);
}

function createFarmerRow(int $userId): void
{
    Database::getConnection()->prepare(
        "INSERT INTO farmers (user_id, village, district_id, state, pincode, verification_status, verified_at)
         VALUES (?, 'P13 Village', 1, 'Maharashtra', '411001', 'APPROVED', NOW())
         ON DUPLICATE KEY UPDATE verification_status = 'APPROVED'"
    )->execute([$userId]);
}

function grantPermission(int $userId, string $permission, int $createdBy = 1): void
{
    (new \App\Services\RbacService())->grantPermission($userId, $permission, $createdBy);
}

function revokePermission(int $userId, string $permission, int $createdBy = 1): void
{
    (new \App\Services\RbacService())->revokePermission($userId, $permission, $createdBy);
}

setSetting('booking.horizon_days', '7');
setSetting('booking.min_lead_hours', '0');
setSetting('booking.cancel_lead_minutes', '120');
setSetting('booking.max_active', '2');
setSetting('auto_confirm_on_payment', '1');
setSetting('queue.avg_minutes_per_token', '10');
setSetting('queue.grace_no_show_minutes', '0');
setSetting('queue.recall_limit', '1');
setSetting('queue.notify_threshold', '3');
setSetting('procurement.approval_required', '1');
setSetting('procurement.max_photos', '3');
setSetting('procurement.weight_precision', '2');
setSetting('payment.allow_operator_release', '0');
(new \App\Services\RbacService())->clearAllCache();

$farmerMobiles = [];
function newFarmer(int $idx, string $suffix): array
{
    global $farmerMobiles;
    $mobile = '9181' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT) . str_pad((string) $idx, 1, '0', STR_PAD_LEFT);
    $id = createTestUser('fa_p13_' . $idx . '_' . $suffix, 'P13 Farmer ' . $idx, 5, $mobile, 'FaP13Test@1234');
    createFarmerRow($id);
    return ['id' => $id, 'mobile' => $mobile];
}

$farmer1 = newFarmer(1, $suffix);
$farmer2 = newFarmer(2, $suffix);

$opMobile = '9282' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$op2Mobile = '9283' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$mgrMobile = '9284' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$daMobile = '9285' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$opId = createTestUser('op_p13_' . $suffix, 'P13 Operator', 4, $opMobile, 'OpP13Test@1234');
$op2Id = createTestUser('op2_p13_' . $suffix, 'P13 Other Operator', 4, $op2Mobile, 'OpP13Test@1234');
$mgrId = createTestUser('mgr_p13_' . $suffix, 'P13 Manager', 3, $mgrMobile, 'MgrP13Test@1234');
$daId = createTestUser('da_p13_' . $suffix, 'P13 District Admin', 2, $daMobile, 'DaP13Test@1234');

$centreStaff = Database::getConnection()->prepare(
    "INSERT INTO centre_staff (user_id, centre_id, role)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE centre_id = VALUES(centre_id), deleted_at = NULL"
);
$centreStaff->execute([$opId, $c1Id, 'CENTRE_OPERATOR']);
$centreStaff->execute([$op2Id, $c2Id, 'CENTRE_OPERATOR']);
$centreStaff->execute([$mgrId, $c1Id, 'CENTRE_MANAGER']);
$centreStaff->execute([$daId, $c1Id, 'DISTRICT_ADMIN']);

echo "  Wheat=$wheatId  baseRate=$baseRate" . PHP_EOL;
echo "  Centre1=$c1Id ($c1Code)  Centre2=$c2Id ($c2Code)  Slots=$slot1/$slot2/$slot3" . PHP_EOL;

function login(string $mobile, string $password): string
{
    global $base;
    $res = rawCall('POST', "$base/api/v1/auth/login", json_encode(['mobile' => $mobile, 'password' => $password]));
    $data = jdec($res['body']);
    if ($res['status'] !== 200) {
        echo "  LOGIN FAIL $mobile: " . ($data['error']['message'] ?? 'unknown') . PHP_EOL;
    }
    return (string) ($data['data']['access_token'] ?? '');
}

$opToken = login($opMobile, 'OpP13Test@1234');
$op2Token = login($op2Mobile, 'OpP13Test@1234');
$mgrToken = login($mgrMobile, 'MgrP13Test@1234');
$daToken = login($daMobile, 'DaP13Test@1234');
$f1Token = login($farmer1['mobile'], 'FaP13Test@1234');
$f2Token = login($farmer2['mobile'], 'FaP13Test@1234');
check('all participants logged in', $opToken !== '' && $op2Token !== '' && $mgrToken !== '' && $daToken !== '' && $f1Token !== '' && $f2Token !== '');

echo "\n== Crop rate management (district admin) ==" . PHP_EOL;
$noPermRate = rawCall('POST', "$base/api/v1/admin/crop-rates", json_encode([
    'crop_id' => $wheatId,
    'centre_id' => $c1Id,
    'rate_per_kg' => 25.00,
    'effective_from' => $today,
]), $mgrToken);
check('manager create rate -> 403', $noPermRate['status'] === 403);

$rateResp = jdec(rawCall('POST', "$base/api/v1/admin/crop-rates", json_encode([
    'crop_id' => $wheatId,
    'centre_id' => $c1Id,
    'rate_per_kg' => 25.00,
    'effective_from' => $today,
]), $daToken)['body']);
$overrideRate = $rateResp['data'] ?? [];
$overrideId = (int) ($overrideRate['id'] ?? 0);
check('DA creates centre override rate -> 201', $overrideId > 0 && ($overrideRate['rate_per_kg'] ?? 0) == 25.00 && (int) ($overrideRate['centre_id'] ?? 0) === $c1Id);

$r1 = jdec(rawCall('GET', "$base/api/v1/crops/{$wheatId}/rates?centre_id={$c1Id}&date={$today}", null, $mgrToken)['body'])['data'] ?? [];
check('crops/{id}/rates centre=c1 -> override 25.00', (float) ($r1['rate_per_kg'] ?? 0) === 25.00 && ($r1['rate_source'] ?? '') === 'centre_override');
$r2 = jdec(rawCall('GET', "$base/api/v1/crops/{$wheatId}/rates?centre_id={$c2Id}&date={$today}", null, $mgrToken)['body'])['data'] ?? [];
check('crops/{id}/rates centre=c2 -> base', (float) ($r2['rate_per_kg'] ?? 0) === $baseRate && ($r2['rate_source'] ?? '') === 'base');
$r3 = jdec(rawCall('GET', "$base/api/v1/crops/{$wheatId}/rates?date={$today}", null, $f1Token)['body'])['data'] ?? [];
check('crops/{id}/rates without centre -> base', (float) ($r3['rate_per_kg'] ?? 0) === $baseRate);

$rateList = jdec(rawCall('GET', "$base/api/v1/admin/crop-rates?crop_id={$wheatId}", null, $daToken)['body']);
$rateItems = $rateList['data'] ?? [];
check('admin list rates -> has base + override', ($rateList['success'] ?? false) && count($rateItems) >= 2);
$putRate = jdec(rawCall('PUT', "$base/api/v1/admin/crop-rates/{$overrideId}", json_encode(['rate_per_kg' => 24.50]), $daToken)['body']);
check('DA updates override rate -> 24.50', ($putRate['data']['rate_per_kg'] ?? 0) == 24.50);
$putBack = jdec(rawCall('PUT', "$base/api/v1/admin/crop-rates/{$overrideId}", json_encode(['rate_per_kg' => 25.00]), $daToken)['body']);
check('DA updates override back -> 25.00', ($putBack['data']['rate_per_kg'] ?? 0) == 25.00);

echo "\n== Booking1 (farmer1 -> c1) and queue ==" . PHP_EOL;
$b1 = jdec(rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slot1,
    'centre_id' => $c1Id,
    'crops' => [['crop_id' => $wheatId, 'qty' => 100]],
]), $f1Token)['body']);
$booking1Id = (int) ($b1['data']['booking']['id'] ?? 0);
check('booking1 CONFIRMED', $b1['success'] ?? false === true && $booking1Id > 0);

$q1 = Database::selectOne("SELECT id FROM queue_entries WHERE booking_id = ? ORDER BY id ASC", [$booking1Id]);
$entry1Id = (int) $q1['id'];

$cn = jdec(rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c1Id, 'date' => $tomorrow]), $opToken)['body']);
check('call-next -> farmer1 CALLED', ($cn['data']['entry']['booking_id'] ?? 0) === $booking1Id && ($cn['data']['entry']['status'] ?? '') === 'CALLED');

$st = jdec(rawCall('POST', "$base/api/v1/operator/queue/{$entry1Id}/start", json_encode([]), $opToken)['body']);
$procs = $st['data']['procurements'] ?? [];
check('start -> 1 procurement', ($st['success'] ?? false) && count($procs) === 1);
$p1 = (int) ($procs[0]['id'] ?? 0);

$cap = jdec(rawCall('PUT', "$base/api/v1/operator/procurements/{$p1}", json_encode([
    'accepted_weight' => 80.5,
    'damaged_qty' => 0,
    'grade' => 'A',
    'moisture_pct' => 12,
]), $opToken)['body']);
check('capture 80.5kg -> 200', ($cap['success'] ?? false) && (float) ($cap['data']['procurement']['accepted_weight'] ?? 0) === 80.5);

$sub = jdec(rawCall('POST', "$base/api/v1/operator/procurements/{$p1}/submit", json_encode([]), $opToken)['body']);
check('submit -> PENDING_APPROVAL', ($sub['success'] ?? false) && ($sub['data']['procurement']['status'] ?? '') === 'PENDING_APPROVAL');

echo "\n== Approve -> payment auto-created with override rate ==" . PHP_EOL;
$ap = jdec(rawCall('POST', "$base/api/v1/admin/approvals/{$p1}/approve", json_encode([]), $mgrToken)['body']);
check('approve -> VERIFIED', ($ap['success'] ?? false) && ($ap['data']['procurement']['status'] ?? '') === 'VERIFIED');

$pay1Row = Database::selectOne("SELECT * FROM payments WHERE procurement_id = ?", [$p1]);
$pay1Id = (int) ($pay1Row['id'] ?? 0);
check('payment auto-created for procurement', $pay1Id > 0);
check('payment status PENDING', ($pay1Row['status'] ?? '') === 'PENDING');
check('payment amount = 80.5 x 25.00 override = 2012.50', (float) ($pay1Row['amount'] ?? 0) === 2012.50);
check('payment rate_per_kg stored 25.00', (float) ($pay1Row['rate_per_kg'] ?? 0) === 25.00);

$dupPay = Database::selectOne("SELECT id FROM payments WHERE procurement_id = ?", [$p1]);
$payCount = Database::selectOne("SELECT COUNT(*) AS c FROM payments WHERE procurement_id = ?", [$p1]);
check('only ONE payment per procurement (unique)', (int) ($payCount['c'] ?? 0) === 1);

echo "\n== Release validation ==" . PHP_EOL;
$noRef = rawCall('PUT', "$base/api/v1/operator/payments/{$pay1Id}/release", json_encode(['method' => 'upi']), $mgrToken);
check('release without ref -> 400 REFERENCE_REQUIRED', $noRef['status'] === 400 && bodyContains($noRef['body'], 'REFERENCE_REQUIRED'));
$badMethod = rawCall('PUT', "$base/api/v1/operator/payments/{$pay1Id}/release", json_encode(['method' => 'crypto', 'payment_reference' => 'X-001']), $mgrToken);
check('release bad method -> 400 METHOD_INVALID', $badMethod['status'] === 400 && bodyContains($badMethod['body'], 'METHOD_INVALID'));
$opRelease = rawCall('PUT', "$base/api/v1/operator/payments/{$pay1Id}/release", json_encode(['method' => 'upi', 'payment_reference' => 'OP-REF-1']), $opToken);
check('operator release when policy off -> 403', $opRelease['status'] === 403);

echo "\n== Manager release + idempotency + duplicate guard ==" . PHP_EOL;
$rel = jdec(rawCall('PUT', "$base/api/v1/operator/payments/{$pay1Id}/release", json_encode(['method' => 'upi', 'payment_reference' => 'UPI-REF-0001']), $mgrToken)['body']);
$relP = $rel['data'] ?? [];
check('manager release -> 200 RELEASED', ($rel['success'] ?? false) && ($relP['status'] ?? '') === 'RELEASED' && ($relP['payment_reference'] ?? '') === 'UPI-REF-0001' && ($relP['released_by'] ?? 0) === $mgrId && ($relP['released_at'] ?? '') !== null);

$rel2 = jdec(rawCall('PUT', "$base/api/v1/operator/payments/{$pay1Id}/release", json_encode(['method' => 'upi', 'payment_reference' => 'UPI-REF-0001']), $mgrToken)['body']);
check('idempotent release (same ref+method) -> existing, no dup', ($rel2['success'] ?? false) && ($rel2['data']['id'] ?? 0) === $pay1Id && count(Database::select("SELECT id FROM payments WHERE procurement_id = ?", [$p1])) === 1);

$rel3 = rawCall('PUT', "$base/api/v1/operator/payments/{$pay1Id}/release", json_encode(['method' => 'upi', 'payment_reference' => 'UPI-REF-9999']), $mgrToken);
check('different ref on RELEASED -> 409 DUPLICATE_RELEASE', $rel3['status'] === 409 && bodyContains($rel3['body'], 'DUPLICATE_RELEASE'));

echo "\n== Cancel/reverse state guard ==" . PHP_EOL;
$canRel = rawCall('POST', "$base/api/v1/admin/payments/{$pay1Id}/cancel", json_encode(['reason' => 'oops']), $mgrToken);
check('cancel RELEASED -> 409', $canRel['status'] === 409 && bodyContains($canRel['body'], 'PAYMENT_STATUS_INVALID'));

$revMgr = rawCall('POST', "$base/api/v1/admin/payments/{$pay1Id}/reverse", json_encode(['reason' => 'wrong credit']), $mgrToken);
check('manager reverse -> 403 (district+ required)', $revMgr['status'] === 403);

$rev = jdec(rawCall('POST', "$base/api/v1/admin/payments/{$pay1Id}/reverse", json_encode(['reason' => 'wrong credit']), $daToken)['body']);
$revP = $rev['data'] ?? [];
check('DA reverse -> 200 REVERSED', ($rev['success'] ?? false) && ($revP['status'] ?? '') === 'REVERSED' && ($revP['reversal_reason'] ?? '') === 'wrong credit' && ($revP['reversed_by'] ?? 0) === $daId);

$canRev = rawCall('POST', "$base/api/v1/admin/payments/{$pay1Id}/cancel", json_encode(['reason' => 'x']), $mgrToken);
check('cancel REVERSED -> 409', $canRev['status'] === 409 && bodyContains($canRev['body'], 'already reversed'));

echo "\n== Release double-guard (operator with override) ==" . PHP_EOL;
grantPermission($opId, 'payments.release', $daId);
(new \App\Services\RbacService())->clearAllCache();

$b3 = jdec(rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slot3,
    'centre_id' => $c1Id,
    'crops' => [['crop_id' => $wheatId, 'qty' => 50]],
]), $f1Token)['body']);
$booking3Id = (int) ($b3['data']['booking']['id'] ?? 0);
check('booking3 CONFIRMED (operator-release test)', ($b3['success'] ?? false) && $booking3Id > 0);
$q3 = Database::selectOne("SELECT id FROM queue_entries WHERE booking_id = ? ORDER BY id ASC", [$booking3Id]);
$entry3Id = (int) $q3['id'];
rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c1Id, 'date' => $tomorrow]), $opToken);
rawCall('POST', "$base/api/v1/operator/queue/{$entry3Id}/start", json_encode([]), $opToken);
$p3rows = Database::select("SELECT id FROM procurements WHERE booking_id = ? ORDER BY id", [$booking3Id]);
$p3 = (int) ($p3rows[0]['id'] ?? 0);
rawCall('PUT', "$base/api/v1/operator/procurements/{$p3}", json_encode(['accepted_weight' => 10, 'damaged_qty' => 0, 'grade' => 'A']), $opToken);
rawCall('POST', "$base/api/v1/operator/procurements/{$p3}/submit", json_encode([]), $opToken);
rawCall('POST', "$base/api/v1/admin/approvals/{$p3}/approve", json_encode([]), $mgrToken);
$pay3Row = Database::selectOne("SELECT * FROM payments WHERE procurement_id = ?", [$p3]);
$pay3Id = (int) ($pay3Row['id'] ?? 0);
check('payment3 auto-created PENDING', $pay3Id > 0 && ($pay3Row['status'] ?? '') === 'PENDING');
check('payment3 amount = 10 x 25.00 = 250.00', (float) ($pay3Row['amount'] ?? 0) === 250.00);

$opReleaseOff = rawCall('PUT', "$base/api/v1/operator/payments/{$pay3Id}/release", json_encode(['method' => 'cash', 'payment_reference' => 'OP-CASH-001']), $opToken);
check('operator (override granted) release when policy OFF -> 403 CENTRE_MISMATCH', $opReleaseOff['status'] === 403 && bodyContains($opReleaseOff['body'], 'CENTRE_MISMATCH'));

setSetting('payment.allow_operator_release', '1');
(new \App\Services\RbacService())->clearAllCache();
$opReleaseOn = jdec(rawCall('PUT', "$base/api/v1/operator/payments/{$pay3Id}/release", json_encode(['method' => 'cash', 'payment_reference' => 'OP-CASH-001']), $opToken)['body']);
check('operator release when policy ON -> 200 RELEASED', ($opReleaseOn['success'] ?? false) && ($opReleaseOn['data']['status'] ?? '') === 'RELEASED');

setSetting('payment.allow_operator_release', '0');
revokePermission($opId, 'payments.release', $daId);
(new \App\Services\RbacService())->clearAllCache();

echo "\n== Base-rate centre (c2) flow -> payment uses base rate ==" . PHP_EOL;
$f2Slot2 = $slot2;
$b2 = jdec(rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $f2Slot2,
    'centre_id' => $c2Id,
    'crops' => [['crop_id' => $wheatId, 'qty' => 80]],
]), $f2Token)['body']);
$booking2Id = (int) ($b2['data']['booking']['id'] ?? 0);
check('booking2 CONFIRMED at c2', $b2['success'] ?? false === true && $booking2Id > 0);
$q2 = Database::selectOne("SELECT id FROM queue_entries WHERE booking_id = ? ORDER BY id ASC", [$booking2Id]);
$entry2Id = (int) $q2['id'];
rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c2Id, 'date' => $tomorrow]), $op2Token);
rawCall('POST', "$base/api/v1/operator/queue/{$entry2Id}/start", json_encode([]), $op2Token);
$p2rows = Database::select("SELECT id FROM procurements WHERE booking_id = ? ORDER BY id", [$booking2Id]);
$p2 = (int) ($p2rows[0]['id'] ?? 0);
rawCall('PUT', "$base/api/v1/operator/procurements/{$p2}", json_encode(['accepted_weight' => 40, 'damaged_qty' => 0, 'grade' => 'B']), $op2Token);
rawCall('POST', "$base/api/v1/operator/procurements/{$p2}/submit", json_encode([]), $op2Token);
$ap2 = jdec(rawCall('POST', "$base/api/v1/admin/approvals/{$p2}/approve", json_encode([]), $daToken)['body']);
check('DA approves c2 procurement -> VERIFIED', ($ap2['success'] ?? false) && ($ap2['data']['procurement']['status'] ?? '') === 'VERIFIED');
$pay2Row = Database::selectOne("SELECT * FROM payments WHERE procurement_id = ?", [$p2]);
$pay2Id = (int) ($pay2Row['id'] ?? 0);
$expectedP2 = round(40.0 * $baseRate, 2);
check('payment2 amount = 40 x baseRate = ' . $expectedP2, (float) ($pay2Row['amount'] ?? 0) === $expectedP2 && (float) ($pay2Row['rate_per_kg'] ?? 0) === $baseRate);

echo "\n== Cancel PENDING payment + guards ==" . PHP_EOL;
$can0 = jdec(rawCall('POST', "$base/api/v1/admin/payments/{$pay2Id}/cancel", json_encode(['reason' => 'no stock at intake']), $daToken)['body']);
$can0P = $can0['data'] ?? [];
check('DA cancel PENDING -> 200 CANCELLED', ($can0['success'] ?? false) && ($can0P['status'] ?? '') === 'CANCELLED' && ($can0P['cancelled_by'] ?? 0) === $daId);
$relCancelled = rawCall('PUT', "$base/api/v1/operator/payments/{$pay2Id}/release", json_encode(['method' => 'upi', 'payment_reference' => 'UPI-X']), $daToken);
check('release CANCELLED -> 409', $relCancelled['status'] === 409 && bodyContains($relCancelled['body'], 'PAYMENT_STATUS_INVALID'));
$revCancelled = rawCall('POST', "$base/api/v1/admin/payments/{$pay2Id}/reverse", json_encode(['reason' => 'x']), $daToken);
check('reverse CANCELLED -> 409', $revCancelled['status'] === 409 && bodyContains($revCancelled['body'], 'PAYMENT_STATUS_INVALID'));

echo "\n== Farmer statement + ownership ==" . PHP_EOL;
$stmt1 = jdec(rawCall('GET', "$base/api/v1/my/payments", null, $f1Token)['body']);
$stmt1Items = $stmt1['data']['items'] ?? [];
check('farmer1 statement count = 2', ($stmt1['success'] ?? false) && count($stmt1Items) === 2);
$stmtStatuses = array_values(array_unique(array_map(fn($p) => $p['status'] ?? '', $stmt1Items)));
sort($stmtStatuses);
check('farmer1 statuses include RELEASED + REVERSED', in_array('RELEASED', $stmtStatuses, true) && in_array('REVERSED', $stmtStatuses, true));

$stmtShow = jdec(rawCall('GET', "$base/api/v1/my/payments/{$pay1Id}", null, $f1Token)['body']);
check('farmer1 own payment detail -> 200 with reversal_reason', ($stmtShow['success'] ?? false) && ($stmtShow['data']['payment']['reversal_reason'] ?? '') === 'wrong credit');

$wrong1 = rawCall('GET', "$base/api/v1/my/payments/{$pay1Id}", null, $f2Token);
check('farmer2 reading farmer1 payment -> 404 PAYMENT_NOT_FOUND', $wrong1['status'] === 404 && bodyContains($wrong1['body'], 'PAYMENT_NOT_FOUND'));
$wrong2 = rawCall('GET', "$base/api/v1/my/payments/{$pay2Id}", null, $f1Token);
check('farmer1 reading farmer2 payment -> 404', $wrong2['status'] === 404);

echo "\n== Operator/admin lists + scope ==" . PHP_EOL;
$opList = jdec(rawCall('GET', "$base/api/v1/operator/payments?status=REVERSED", null, $opToken)['body']);
$opItems = $opList['data'] ?? [];
check('operator list status=REVERSED -> payment1 visible', ($opList['success'] ?? false) && count($opItems) === 1 && (int) ($opItems[0]['id'] ?? 0) === $pay1Id);

$opQ = jdec(rawCall('GET', "$base/api/v1/operator/payments?q=P13%20Farmer%201", null, $opToken)['body']);
check('operator list q=farmername -> returns rows', ($opQ['success'] ?? false) && count($opQ['data'] ?? []) >= 1);

$opShow = rawCall('GET', "$base/api/v1/operator/payments/{$pay1Id}", null, $opToken);
check('operator show own-centre -> 200', $opShow['status'] === 200);
$op2List = jdec(rawCall('GET', "$base/api/v1/operator/payments?status=CANCELLED", null, $op2Token)['body']);
$op2Items = $op2List['data'] ?? [];
check('other operator list status=CANCELLED -> payment2 visible', ($op2List['success'] ?? false) && count($op2Items) === 1 && (int) ($op2Items[0]['id'] ?? 0) === $pay2Id);
$op2Show = rawCall('GET', "$base/api/v1/operator/payments/{$pay1Id}", null, $op2Token);
check('other operator show payment1 -> 403', $op2Show['status'] === 403);

$daList = jdec(rawCall('GET', "$base/api/v1/admin/payments", null, $daToken)['body']);
$daItems = $daList['data'] ?? [];
$daIds = array_map(fn($it) => (int) ($it['id'] ?? 0), $daItems);
check('DA admin list -> sees district payments', ($daList['success'] ?? false) && in_array($pay1Id, $daIds, true) && in_array($pay2Id, $daIds, true) && in_array($pay3Id, $daIds, true));
$daShow = rawCall('GET', "$base/api/v1/admin/payments/{$pay1Id}", null, $daToken);
check('DA admin show -> 200', $daShow['status'] === 200);
$mgrShow2 = rawCall('GET', "$base/api/v1/admin/payments/{$pay2Id}", null, $mgrToken);
check('manager admin show c2 payment -> 403', $mgrShow2['status'] === 403);

echo "\n== Notification events logged (Phase 14 handles sending) ==" . PHP_EOL;
$notifs = Database::select(
    "SELECT COUNT(*) AS c FROM notification_logs WHERE user_id = ? AND event_type IN ('payment_released', 'payment_reversed')",
    [$farmer1['id']]
);
check('farmer1 release/reverse notification events queued', (int) ($notifs[0]['c'] ?? 0) >= 2);

echo "\n=== Result: {$pass} passed, {$fail} failed ===" . PHP_EOL;
exit($fail > 0 ? 1 : 0);