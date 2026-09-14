<?php

declare(strict_types=1);

// Phase 12 E2E verification via raw HTTP against php -S 127.0.0.1:8090.
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

echo "=== Phase 12 baseline setup ===" . PHP_EOL;

$suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$c1Code = 'P12A' . $suffix;
$c1 = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$c1Code]);
if ($c1 === null) {
    $c1Id = (int) Database::insert('procurement_centres', [
        'name' => 'P12 Proc Centre ' . $suffix,
        'code' => $c1Code,
        'district_id' => 1,
        'address' => 'Phase 12 Proc',
        'working_hours_start' => '06:00:00',
        'working_hours_end' => '22:00:00',
        'daily_capacity' => 100,
        'slot_duration_minutes' => 30,
        'status' => 'ACTIVE',
    ]);
} else {
    $c1Id = (int) $c1['id'];
    Database::update('procurement_centres', ['status' => 'ACTIVE', 'deleted_at' => null], 'id = ?', [$c1Id]);
}

$c2Code = 'P12B' . $suffix;
$c2 = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$c2Code]);
if ($c2 === null) {
    $c2Id = (int) Database::insert('procurement_centres', [
        'name' => 'P12 Other Centre ' . $suffix,
        'code' => $c2Code,
        'district_id' => 1,
        'address' => 'Phase 12 Other',
        'working_hours_start' => '06:00:00',
        'working_hours_end' => '22:00:00',
        'daily_capacity' => 50,
        'slot_duration_minutes' => 30,
        'status' => 'ACTIVE',
    ]);
} else {
    $c2Id = (int) $c2['id'];
    Database::update('procurement_centres', ['status' => 'ACTIVE', 'deleted_at' => null], 'id = ?', [$c2Id]);
}

foreach ([$c1Id, $c2Id] as $centreId) {
    Database::getConnection()->prepare("DELETE p FROM procurements p INNER JOIN bookings b ON b.id = p.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM queue_entries WHERE centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE t FROM tokens t INNER JOIN bookings b ON b.id = t.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE bc FROM booking_crops bc INNER JOIN bookings b ON b.id = bc.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM bookings WHERE centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM slots WHERE centre_id = ?")->execute([$centreId]);
}

$cropRows = Database::select("SELECT id, name FROM crops WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 4");
if (count($cropRows) < 3) {
    while (count($cropRows) < 3) {
        $name = 'P12Crop' . $suffix . count($cropRows);
        $id = (int) Database::insert('crops', ['name' => $name, 'is_active' => 1]);
        $cropRows[] = ['id' => $id, 'name' => $name];
    }
}
$crop1 = (int) $cropRows[0]['id'];
$crop2 = (int) $cropRows[1]['id'];
$crop3 = (int) $cropRows[2]['id'];

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

function createTestUser(string $username, string $name, int $roleId, string $mobile, string $password): int
{
    $existing = Database::selectOne("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing !== null) {
        $userId = (int) $existing['id'];
        Database::getConnection()->prepare("DELETE FROM notification_logs WHERE user_id = ?")->execute([$userId]);
        Database::getConnection()->prepare("DELETE FROM farmers WHERE user_id = ?")->execute([$userId]);
        Database::getConnection()->prepare("DELETE FROM centre_staff WHERE user_id = ?")->execute([$userId]);
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
         VALUES (?, 'P12 Village', 1, 'Maharashtra', '411001', 'APPROVED', NOW())
         ON DUPLICATE KEY UPDATE verification_status = 'APPROVED'"
    )->execute([$userId]);
}

setSetting('booking.horizon_days', '7');
setSetting('booking.min_lead_hours', '0');
setSetting('booking.cancel_lead_minutes', '120');
setSetting('booking.max_active', '1');
setSetting('auto_confirm_on_payment', '1');
setSetting('queue.avg_minutes_per_token', '10');
setSetting('queue.grace_no_show_minutes', '0');
setSetting('queue.recall_limit', '1');
setSetting('queue.notify_threshold', '3');
setSetting('procurement.approval_required', '1');
setSetting('procurement.max_photos', '3');
setSetting('procurement.weight_precision', '2');
(new \App\Services\RbacService())->clearAllCache();

$farmerMobiles = [];
function newFarmer(int $idx, string $suffix): array
{
    global $farmerMobiles;
    $mobile = '9181' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT) . str_pad((string) $idx, 1, '0', STR_PAD_LEFT);
    $id = createTestUser('fa_p12_' . $idx . '_' . $suffix, 'P12 Farmer ' . $idx, 5, $mobile, 'FaP12Test@1234');
    createFarmerRow($id);
    return ['id' => $id, 'mobile' => $mobile];
}

$farmer1 = newFarmer(1, $suffix);
$farmer2 = newFarmer(2, $suffix);

$opMobile = '9182' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$op2Mobile = '9183' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$mgrMobile = '9184' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$opId = createTestUser('op_p12_' . $suffix, 'P12 Operator', 4, $opMobile, 'OpP12Test@1234');
$op2Id = createTestUser('op2_p12_' . $suffix, 'P12 Other Operator', 4, $op2Mobile, 'OpP12Test@1234');
$mgrId = createTestUser('mgr_p12_' . $suffix, 'P12 Manager', 3, $mgrMobile, 'MgrP12Test@1234');

$centreStaff = Database::getConnection()->prepare(
    "INSERT INTO centre_staff (user_id, centre_id, role)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE centre_id = VALUES(centre_id), deleted_at = NULL"
);
$centreStaff->execute([$opId, $c1Id, 'CENTRE_OPERATOR']);
$centreStaff->execute([$op2Id, $c2Id, 'CENTRE_OPERATOR']);
$centreStaff->execute([$mgrId, $c1Id, 'CENTRE_MANAGER']);

echo "  Centre1=$c1Id ($c1Code)  Centre2=$c2Id ($c2Code)  Slot1=$slot1" . PHP_EOL;
echo "  Farmer1={$farmer1['id']}  Farmer2={$farmer2['id']}  Operator=$opId  OtherOperator=$op2Id  Manager=$mgrId" . PHP_EOL;

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

$opToken = login($opMobile, 'OpP12Test@1234');
$op2Token = login($op2Mobile, 'OpP12Test@1234');
$mgrToken = login($mgrMobile, 'MgrP12Test@1234');
$f1Token = login($farmer1['mobile'], 'FaP12Test@1234');
$f2Token = login($farmer2['mobile'], 'FaP12Test@1234');
check('all participants logged in', $opToken !== '' && $op2Token !== '' && $mgrToken !== '' && $f1Token !== '' && $f2Token !== '');

echo "\n== Bookings (farmer1: 3 crops, farmer2: 1 crop) ==" . PHP_EOL;
$b1 = jdec(rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slot1,
    'centre_id' => $c1Id,
    'crops' => [
        ['crop_id' => $crop1, 'qty' => 100],
        ['crop_id' => $crop2, 'qty' => 80],
        ['crop_id' => $crop3, 'qty' => 60],
    ],
]), $f1Token)['body']);
$booking1Id = (int) ($b1['data']['booking']['id'] ?? 0);
check('booking1 CONFIRMED with 3 crops', $b1['success'] ?? false === true && $booking1Id > 0);

$b2 = jdec(rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slot1,
    'centre_id' => $c1Id,
    'crops' => [['crop_id' => $crop1, 'qty' => 90]],
]), $f2Token)['body']);
$booking2Id = (int) ($b2['data']['booking']['id'] ?? 0);
check('booking2 CONFIRMED', $b2['success'] ?? false === true && $booking2Id > 0);

$qrows = Database::select(
    "SELECT * FROM queue_entries WHERE booking_id IN (?, ?) ORDER BY id ASC",
    [$booking1Id, $booking2Id]
);
check('queue entries created for both bookings', count($qrows) === 2);
$entry1Id = (int) $qrows[0]['id'];
$entry2Id = (int) $qrows[1]['id'];

echo "\n== Call next farmer1 (multi-crop) ==" . PHP_EOL;
$cn = jdec(rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c1Id, 'date' => $tomorrow]), $opToken)['body']);
check('call-next -> farmer1 CALLED', ($cn['data']['entry']['booking_id'] ?? 0) === $booking1Id && ($cn['data']['entry']['status'] ?? '') === 'CALLED');

echo "\n== Start procurement (3 crops) ==" . PHP_EOL;
$st = jdec(rawCall('POST', "$base/api/v1/operator/queue/{$entry1Id}/start", json_encode([]), $opToken)['body']);
$startData = $st['data'] ?? [];
$procs = $startData['procurements'] ?? [];
check('start -> 200 with 3 procurements', $st['success'] ?? false && count($procs) === 3);
check('all procurements PENDING', count(array_filter($procs, fn($p) => ($p['status'] ?? '') === 'PENDING')) === 3);
$entry1State = Database::selectOne("SELECT status FROM queue_entries WHERE id = ?", [$entry1Id]);
check('queue entry -> IN_PROGRESS', ($entry1State['status'] ?? '') === 'IN_PROGRESS');
$p1 = (int) ($procs[0]['id'] ?? 0);
$p2 = (int) ($procs[1]['id'] ?? 0);

echo "\n== Capture validation ==" . PHP_EOL;
$neg = rawCall('PUT', "$base/api/v1/operator/procurements/{$p1}", json_encode(['accepted_weight' => -5, 'damaged_qty' => 0]), $opToken);
check('negative accepted_weight -> 400 WEIGHT_INVALID', $neg['status'] === 400 && bodyContains($neg['body'], 'WEIGHT_INVALID'));
$over = rawCall('PUT', "$base/api/v1/operator/procurements/{$p1}", json_encode(['accepted_weight' => 95, 'damaged_qty' => 9999]), $opToken);
check('damaged_qty over booked -> 400 WEIGHT_INVALID', $over['status'] === 400 && bodyContains($over['body'], 'WEIGHT_INVALID'));

$cap = jdec(rawCall('PUT', "$base/api/v1/operator/procurements/{$p1}", json_encode([
    'accepted_weight' => 95.5,
    'damaged_qty' => 4.5,
    'grade' => 'A',
    'moisture_pct' => 12.5,
    'quality_notes' => 'good quality',
]), $opToken)['body']);
$capP = $cap['data']['procurement'] ?? [];
check('valid capture -> 200 weight/grade/moisture', $cap['success'] ?? false && (float) ($capP['accepted_weight'] ?? 0) === 95.5 && (float) ($capP['damaged_qty'] ?? 0) === 4.5 && ($capP['grade'] ?? '') === 'A' && (float) ($capP['moisture_pct'] ?? 0) === 12.5);

echo "\n== Submit with approval_required=1 ==" . PHP_EOL;
$sub = jdec(rawCall('POST', "$base/api/v1/operator/procurements/{$p1}/submit", json_encode([]), $opToken)['body']);
check('submit -> PENDING_APPROVAL', $sub['success'] ?? false && ($sub['data']['procurement']['status'] ?? '') === 'PENDING_APPROVAL');

echo "\n== Reject without reason / with reason ==" . PHP_EOL;
$noReason = rawCall('POST', "$base/api/v1/operator/procurements/{$p2}/reject", json_encode([]), $opToken);
check('reject no reason -> 400 REASON_REQUIRED', $noReason['status'] === 400 && bodyContains($noReason['body'], 'REASON_REQUIRED'));
$rej = jdec(rawCall('POST', "$base/api/v1/operator/procurements/{$p2}/reject", json_encode(['reason' => 'water damage']), $opToken)['body']);
check('reject with reason -> REJECTED', $rej['success'] ?? false && ($rej['data']['procurement']['status'] ?? '') === 'REJECTED' && ($rej['data']['procurement']['rejection_reason'] ?? '') === 'water damage');

echo "\n== Other-centre operator blocked ==" . PHP_EOL;
$oth = rawCall('POST', "$base/api/v1/operator/queue/{$entry2Id}/start", json_encode([]), $op2Token);
check('other-centre operator start -> 403', $oth['status'] === 403);
$oth2 = rawCall('POST', "$base/api/v1/operator/procurements/{$p1}/reject", json_encode(['reason' => 'x']), $op2Token);
check('other-centre operator reject -> 403', $oth2['status'] === 403);

echo "\n== Manager approval -> VERIFIED (amount NULL) ==" . PHP_EOL;
$ap = jdec(rawCall('POST', "$base/api/v1/admin/approvals/{$p1}/approve", json_encode([]), $mgrToken)['body']);
$apP = $ap['data']['procurement'] ?? [];
check('approve -> 200 VERIFIED approved_amount NULL', $ap['success'] ?? false && ($apP['status'] ?? '') === 'VERIFIED' && ($apP['approved_amount'] ?? 'x') === null && ($apP['approved_at'] ?? '') !== null);

echo "\n== VERIFIED immutable ==" . PHP_EOL;
$imm = rawCall('PUT', "$base/api/v1/operator/procurements/{$p1}", json_encode(['accepted_weight' => 90]), $opToken);
check('capture after VERIFIED -> 409 STATUS_IMMUTABLE', $imm['status'] === 409 && bodyContains($imm['body'], 'STATUS_IMMUTABLE'));

echo "\n== Auto policy (approval_required=0) ==" . PHP_EOL;
setSetting('procurement.approval_required', '0');
$cap3 = jdec(rawCall('PUT', "$base/api/v1/operator/procurements/{$procs[2]['id']}", json_encode([
    'accepted_weight' => 55,
    'damaged_qty' => 5,
    'grade' => 'B',
    'moisture_pct' => 14,
]), $opToken)['body']);
$p3 = (int) ($procs[2]['id'] ?? 0);
$sub3 = jdec(rawCall('POST', "$base/api/v1/operator/procurements/{$p3}/submit", json_encode([]), $opToken)['body']);
check('submit auto -> VERIFIED', $sub3['success'] ?? false && ($sub3['data']['procurement']['status'] ?? '') === 'VERIFIED');
setSetting('procurement.approval_required', '1');

echo "\n== Farmer /my/procurements (3 crop lines, independent statuses) ==" . PHP_EOL;
$my = jdec(rawCall('GET', "$base/api/v1/my/procurements", null, $f1Token)['body']);
$myItems = $my['data']['items'] ?? [];
check('my procurements -> 3 items', $my['success'] ?? false && count($myItems) === 3);
$statuses = array_column($myItems, 'status');
sort($statuses);
check('statuses are REJECTED + double VERIFIED', $statuses === ['REJECTED', 'VERIFIED', 'VERIFIED']);

echo "\n== Farmer ownership ==" . PHP_EOL;
$own = rawCall('GET', "$base/api/v1/my/procurements/{$p1}", null, $f1Token);
check('own procurement detail -> 200', $own['status'] === 200);
$wrong = rawCall('GET', "$base/api/v1/my/procurements/{$p2}", null, $f2Token);
check('other farmer procurement -> 404 PROC_NOT_FOUND', $wrong['status'] === 404 && bodyContains($wrong['body'], 'PROC_NOT_FOUND'));

echo "\n== Operator/admin read routes (list + show) ==" . PHP_EOL;
$opList = jdec(rawCall('GET', "$base/api/v1/operator/procurements?status=VERIFIED", null, $opToken)['body']);
$opItems = $opList['data']['items'] ?? [];
check('operator list -> 200 with items', $opList['success'] ?? false && count($opItems) > 0 && ($opItems[0]['status'] ?? '') === 'VERIFIED');
$opShow = rawCall('GET', "$base/api/v1/operator/procurements/{$p1}", null, $opToken);
check('operator show -> 200', $opShow['status'] === 200);
$apprList = jdec(rawCall('GET', "$base/api/v1/admin/approvals?centre_id={$c1Id}", null, $mgrToken)['body']);
$apprItems = $apprList['data']['items'] ?? [];
check('approval list -> 200', $apprList['success'] ?? false);
$apprShow = rawCall('GET', "$base/api/v1/admin/approvals/{$p1}", null, $mgrToken);
check('approval show -> 200', $apprShow['status'] === 200);
$othList = rawCall('GET', "$base/api/v1/operator/procurements/{$p1}", null, $op2Token);
check('other-centre operator show -> 403', $othList['status'] === 403);

echo "\n=== Result: {$pass} passed, {$fail} failed ===" . PHP_EOL;
exit($fail > 0 ? 1 : 0);