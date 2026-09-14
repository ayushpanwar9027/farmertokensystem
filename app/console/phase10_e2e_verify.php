<?php

declare(strict_types=1);

// Phase 10 E2E verification via raw HTTP against php -S 127.0.0.1:8090.
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

// --- Prepare baseline data ---
echo "=== Seeding Phase 10 baseline ===" . PHP_EOL;

$suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
$centreCode = 'P10C' . $suffix;

$centre = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$centreCode]);
if ($centre === null) {
    $centreId = (int) Database::insert('procurement_centres', [
        'name' => 'P10 Booking Centre ' . $suffix,
        'code' => $centreCode,
        'district_id' => 1,
        'address' => 'P10 Test',
        'working_hours_start' => '06:00:00',
        'working_hours_end' => '22:00:00',
        'daily_capacity' => 100,
        'slot_duration_minutes' => 30,
        'status' => 'ACTIVE',
    ]);
} else {
    $centreId = (int) $centre['id'];
    Database::update('procurement_centres', ['status' => 'ACTIVE', 'deleted_at' => null], 'id = ?', [$centreId]);
}
echo "  Centre $centreCode (id=$centreId)" . PHP_EOL;

$farmerMobile = '9123' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$farmer = Database::selectOne("SELECT id FROM users WHERE mobile = ?", [$farmerMobile]);
if ($farmer === null) {
    $farmerId = (int) Database::insert('users', [
        'name' => 'P10 Farmer',
        'mobile' => $farmerMobile,
        'username' => 'fa_p10_' . $suffix,
        'password_hash' => password_hash('FaP10Test@1234', PASSWORD_DEFAULT),
        'role_id' => 5,
        'status' => 'ACTIVE',
        'verification_status' => 'APPROVED',
        'mobile_verified_at' => date('Y-m-d H:i:s'),
        'password_set_at' => date('Y-m-d H:i:s'),
        'is_super_admin' => 0,
    ]);
} else {
    $farmerId = (int) $farmer['id'];
    Database::update('users', ['status' => 'ACTIVE', 'verification_status' => 'APPROVED'], 'id = ?', [$farmerId]);
}
$fr = Database::selectOne("SELECT id FROM farmers WHERE user_id = ?", [$farmerId]);
if ($fr === null) {
    Database::insert('farmers', [
        'user_id' => $farmerId,
        'village' => 'P10 Village',
        'district_id' => 1,
        'state' => 'Maharashtra',
        'pincode' => '411001',
        'verification_status' => 'APPROVED',
        'verified_at' => date('Y-m-d H:i:s'),
    ]);
} else {
    Database::update('farmers', ['verification_status' => 'APPROVED'], 'id = ?', [(int) $fr['id']]);
}
echo "  Farmer user_id=$farmerId" . PHP_EOL;

$crops = Database::select("SELECT id, code FROM crops WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 2");
$crop1 = (int) $crops[0]['id'];
$crop2 = (int) $crops[1]['id'];
echo "  Crops: $crop1, $crop2" . PHP_EOL;

// Clean up any leftover P10 bookings/slots for this centre to keep capacity predictable
Database::getConnection()->prepare("DELETE t FROM tokens t INNER JOIN bookings b ON b.id = t.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
Database::getConnection()->prepare("DELETE bc FROM booking_crops bc INNER JOIN bookings b ON b.id = bc.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
Database::getConnection()->prepare("DELETE FROM bookings WHERE centre_id = ?")->execute([$centreId]);
Database::getConnection()->prepare("DELETE FROM slots WHERE centre_id = ?")->execute([$centreId]);

function setSetting(string $key, $val): void
{
    (new \App\Services\SettingService())->set($key, $val);
}
setSetting('booking.horizon_days', '7');
setSetting('booking.min_lead_hours', '0');
setSetting('booking.cancel_lead_minutes', '120');
setSetting('booking.max_active', '1');
setSetting('auto_confirm_on_payment', '0');

function createSlot(int $centreId, string $date, string $start, string $end, int $capacity): int
{
    $existing = Database::selectOne(
        "SELECT id FROM slots WHERE centre_id = ? AND date = ? AND start_time = ?",
        [$centreId, $date, $start]
    );
    if ($existing !== null) {
        Database::update('slots', ['capacity' => $capacity, 'booked_count' => 0, 'status' => 'ACTIVE'], 'id = ?', [(int) $existing['id']]);
        return (int) $existing['id'];
    }
    return (int) Database::insert('slots', [
        'centre_id' => $centreId,
        'date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'capacity' => $capacity,
        'booked_count' => 0,
        'status' => 'ACTIVE',
        'created_by' => 1,
    ]);
}

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$slotOpen = createSlot($centreId, $tomorrow, '10:00:00', '10:30:00', 10);
$slotFull = createSlot($centreId, $tomorrow, '11:00:00', '11:30:00', 1);
$pastSlot = createSlot($centreId, date('Y-m-d', strtotime('-1 day')), '10:00:00', '10:30:00', 10);

$nearDate = date('Y-m-d');
$nearStart = date('H:i:s', strtotime('+30 minutes'));
if (substr($nearStart, 0, 1) >= '2' && (int) substr($nearStart, 1, 1) >= 3) {
    $nearStart = '20:00:00';
}
$slotNear = createSlot($centreId, $nearDate, $nearStart, '23:30:00', 10);
echo "  Slots: open=$slotOpen full=$slotFull past=$pastSlot near=$slotNear ($nearDate $nearStart)" . PHP_EOL;

$inactiveCentreCode = 'P10INC' . $suffix;
$inactiveCentre = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$inactiveCentreCode]);
if ($inactiveCentre === null) {
    $inactiveCentreId = (int) Database::insert('procurement_centres', [
        'name' => 'P10 Inactive Centre',
        'code' => $inactiveCentreCode,
        'district_id' => 1,
        'address' => 'Inactive',
        'working_hours_start' => '09:00:00',
        'working_hours_end' => '17:00:00',
        'daily_capacity' => 10,
        'slot_duration_minutes' => 30,
        'status' => 'INACTIVE',
    ]);
} else {
    $inactiveCentreId = (int) $inactiveCentre['id'];
}
Database::update('procurement_centres', ['status' => 'INACTIVE', 'deleted_at' => null], 'id = ?', [$inactiveCentreId]);
$inactiveSlot = createSlot($inactiveCentreId, $tomorrow, '10:00:00', '10:30:00', 10);
echo "  Inactive centre id=$inactiveCentreId slot=$inactiveSlot" . PHP_EOL;

(new \App\Services\RbacService())->clearAllCache();

// --- E2E ---
echo "\n== Phase 10 E2E Verification ==" . PHP_EOL;

check('GET /health -> 200', rawCall('GET', "$base/health")['status'] === 200);

$login = rawCall('POST', "$base/api/v1/auth/login", json_encode(['mobile' => $farmerMobile, 'password' => 'FaP10Test@1234']));
$loginData = jdec($login['body']);
$token = $loginData['data']['access_token'] ?? '';
check('Farmer login -> 200 + token', $login['status'] === 200 && $token !== '');

$saLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456781","password":"SaP08Test@1234"}');
$saData = jdec($saLogin['body']);
$saToken = $saData['data']['access_token'] ?? '';
check('SA login -> 200', $saLogin['status'] === 200 && $saToken !== '');

echo "\n== Crop catalog ==" . PHP_EOL;
$cropsRes = rawCall('GET', "$base/api/v1/crops");
$cropsData = jdec($cropsRes['body']);
check('GET /crops -> 200', $cropsRes['status'] === 200);
check('crops list has >= 2', count($cropsData['data']['crops'] ?? []) >= 2);

echo "\n== Create multi-crop booking (PENDING, no token when auto_confirm off) ==" . PHP_EOL;
$createBody = json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slotOpen,
    'centre_id' => $centreId,
    'crops' => [
        ['crop_id' => $crop1, 'qty' => 100],
        ['crop_id' => $crop2, 'qty' => 50],
    ],
]);
$createRes = rawCall('POST', "$base/api/v1/bookings", $createBody, $token);
$createData = jdec($createRes['body']);
$booking = $createData['data']['booking'] ?? [];
$bookingId = (int) ($booking['id'] ?? 0);
check('POST /bookings -> 201', $createRes['status'] === 201);
check('booking is PENDING', ($booking['status'] ?? '') === 'PENDING');
check('booking crop_count 2', (int) ($booking['crop_count'] ?? 0) === 2);
check('booking total_quantity 150', abs((float) ($booking['total_quantity_kg'] ?? 0) - 150) < 0.001);
check('booking has NO token yet', !isset($booking['token']));

echo "\n== DUPLICATE_BOOKING (one active, max_active=1) ==" . PHP_EOL;
$dupRes = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slotFull,
    'centre_id' => $centreId,
    'crops' => [['crop_id' => $crop1, 'qty' => 10]],
]), $token);
check('second active booking -> 409 DUPLICATE_BOOKING', $dupRes['status'] === 409 && bodyContains($dupRes['body'], 'DUPLICATE_BOOKING'));

echo "\n== Cancel within window ==" . PHP_EOL;
$cancelRes = rawCall('POST', "$base/api/v1/bookings/{$bookingId}/cancel", json_encode(['reason' => 'test cancel']), $token);
$cancelData = jdec($cancelRes['body']);
check('POST /bookings/{id}/cancel -> 200', $cancelRes['status'] === 200);
check('cancel -> CANCELLED', ($cancelData['data']['booking']['status'] ?? '') === 'CANCELLED');
$cropsList = jdec(rawCall('GET', "$base/api/v1/bookings/{$bookingId}/crops", null, $token)['body'])['data']['crops'] ?? [];
check('crop lines emptied after cancel', count($cropsList) === 0);

echo "\n== CROP_NOT_FOUND ==" . PHP_EOL;
$badCropRes = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slotOpen,
    'centre_id' => $centreId,
    'crops' => [['crop_id' => 999999, 'qty' => 10]],
]), $token);
check('invalid crop -> 404 CROP_NOT_FOUND', $badCropRes['status'] === 404 && bodyContains($badCropRes['body'], 'CROP_NOT_FOUND'));

echo "\n== BOOKING_WINDOW_CLOSED (past date) ==" . PHP_EOL;
$pastRes = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => date('Y-m-d', strtotime('-1 day')),
    'slot_id' => $pastSlot,
    'centre_id' => $centreId,
    'crops' => [['crop_id' => $crop1, 'qty' => 10]],
]), $token);
check('past-date slot -> 400 BOOKING_WINDOW_CLOSED', $pastRes['status'] === 400 && bodyContains($pastRes['body'], 'BOOKING_WINDOW_CLOSED'));

echo "\n== CENTRE_INACTIVE ==" . PHP_EOL;
$inactiveRes = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $inactiveSlot,
    'centre_id' => $inactiveCentreId,
    'crops' => [['crop_id' => $crop1, 'qty' => 10]],
]), $token);
check('inactive centre -> 400 CENTRE_INACTIVE', $inactiveRes['status'] === 400 && bodyContains($inactiveRes['body'], 'CENTRE_INACTIVE'));

echo "\n== SLOT_FULL (relax max_active for capacity test) ==" . PHP_EOL;
setSetting('booking.max_active', '10');

$f2Mobile = '9124' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$f2 = Database::selectOne("SELECT id FROM users WHERE mobile = ?", [$f2Mobile]);
if ($f2 === null) {
    $f2Id = (int) Database::insert('users', [
        'name' => 'P10 Farmer Two',
        'mobile' => $f2Mobile,
        'username' => 'fa_p10b_' . $suffix,
        'password_hash' => password_hash('FaP10Test@1234', PASSWORD_DEFAULT),
        'role_id' => 5,
        'status' => 'ACTIVE',
        'verification_status' => 'APPROVED',
        'mobile_verified_at' => date('Y-m-d H:i:s'),
        'password_set_at' => date('Y-m-d H:i:s'),
        'is_super_admin' => 0,
    ]);
} else {
    $f2Id = (int) $f2['id'];
}
Database::getConnection()->prepare("INSERT INTO farmers (user_id, village, district_id, state, pincode, verification_status, verified_at)
    VALUES (?, 'P10 Village', 1, 'Maharashtra', '411001', 'APPROVED', NOW())
    ON DUPLICATE KEY UPDATE verification_status = 'APPROVED'")->execute([$f2Id]);
$f2Login = rawCall('POST', "$base/api/v1/auth/login", json_encode(['mobile' => $f2Mobile, 'password' => 'FaP10Test@1234']));
$f2LoginData = jdec($f2Login['body']);
$f2Token = $f2LoginData['data']['access_token'] ?? '';

$fullRes = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slotFull,
    'centre_id' => $centreId,
    'crops' => [['crop_id' => $crop1, 'qty' => 10]],
]), $token);
check('book capacity-1 slot -> 201', $fullRes['status'] === 201);

$fullRes2 = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slotFull,
    'centre_id' => $centreId,
    'crops' => [['crop_id' => $crop1, 'qty' => 10]],
]), $f2Token);
check('book full slot -> 409 SLOT_FULL', $fullRes2['status'] === 409 && bodyContains($fullRes2['body'], 'SLOT_FULL'));
setSetting('booking.max_active', '1');

echo "\n== CANCELLATION_WINDOW_CLOSED ==" . PHP_EOL;
setSetting('booking.max_active', '10');
$nearCreate = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $nearDate,
    'slot_id' => $slotNear,
    'centre_id' => $centreId,
    'crops' => [['crop_id' => $crop1, 'qty' => 5]],
]), $token);
$nearData = jdec($nearCreate['body']);
$nearBookingId = (int) ($nearData['data']['booking']['id'] ?? 0);
check('near slot booking -> 201', $nearCreate['status'] === 201);
$nearCancel = rawCall('POST', "$base/api/v1/bookings/{$nearBookingId}/cancel", null, $token);
check('cancel near slot -> 409 CANCELLATION_WINDOW_CLOSED', $nearCancel['status'] === 409 && bodyContains($nearCancel['body'], 'CANCELLATION_WINDOW_CLOSED'));
setSetting('booking.max_active', '1');

echo "\n== Admin cancel with reason ==" . PHP_EOL;
$adminCancel = rawCall('POST', "$base/api/v1/admin/bookings/{$nearBookingId}/cancel", json_encode(['reason' => 'admin test reason']), $saToken);
$acData = jdec($adminCancel['body']);
check('admin cancel -> 200', $adminCancel['status'] === 200);
check('admin cancel status CANCELLED', ($acData['data']['booking']['status'] ?? '') === 'CANCELLED');
$auditRows = Database::select("SELECT * FROM audit_logs WHERE entity_type = 'booking' AND action = 'BOOKING_CANCELLED' ORDER BY id DESC LIMIT 1");
check('admin cancel audited', isset($auditRows[0]));

echo "\n== Confirm -> token issued (hashed) ==" . PHP_EOL;
setSetting('booking.max_active', '10');
setSetting('auto_confirm_on_payment', '1');
$autoRes = rawCall('POST', "$base/api/v1/bookings", json_encode([
    'booking_date' => $tomorrow,
    'slot_id' => $slotOpen,
    'centre_id' => $centreId,
    'crops' => [['crop_id' => $crop1, 'qty' => 20]],
]), $token);
$autoData = jdec($autoRes['body']);
$autoBooking = $autoData['data']['booking'] ?? [];
check('auto-confirm booking -> 201', $autoRes['status'] === 201);
check('auto-confirm booking CONFIRMED', ($autoBooking['status'] ?? '') === 'CONFIRMED');
check('auto-confirm booking has token', isset($autoBooking['token']));
$displayToken = $autoBooking['token']['token_number'] ?? '';
check('token format matches GKP-YYYY-NNNNN', preg_match('/^[A-Z0-9]+-\d{4}-\d{5}$/', $displayToken) === 1);

$tokenRow = Database::selectOne("SELECT id, token_number, token_hash FROM tokens WHERE booking_id = ?", [(int) ($autoBooking['id'] ?? 0)]);
check('token stored in DB', $tokenRow !== null);
check('token stored hashed (sha256)', $tokenRow !== null && $tokenRow['token_hash'] === hash('sha256', $tokenRow['token_number']));
check('token_hash differs from display copy', $tokenRow !== null && $tokenRow['token_hash'] !== $tokenRow['token_number']);

echo "\n== /my/token ==" . PHP_EOL;
$myTokenRes = rawCall('GET', "$base/api/v1/my/token", null, $token);
$myTokenData = jdec($myTokenRes['body']);
$myTok = $myTokenData['data']['token'] ?? null;
check('GET /my/token -> 200 + active token', $myTokenRes['status'] === 200 && is_array($myTok) && ($myTok['token_number'] ?? '') !== '');

echo "\n== Booking list (own) + admin list ==" . PHP_EOL;
$listRes = rawCall('GET', "$base/api/v1/bookings?status=CONFIRMED", null, $token);
$listData = jdec($listRes['body']);
check('GET /bookings (own) -> 200 + pagination', $listRes['status'] === 200 && isset($listData['meta']['pagination']));

$adminList = rawCall('GET', "$base/api/v1/admin/bookings?centre_id=$centreId", null, $saToken);
check('GET /admin/bookings -> 200', $adminList['status'] === 200);
check('admin list shows bookings', count(jdec($adminList['body'])['data'] ?? []) >= 1);

Database::getConnection()->prepare("UPDATE system_settings SET key_value = '0' WHERE key_name = 'auto_confirm_on_payment'")->execute();

echo "\n=== Result: {$pass} passed, {$fail} failed ===" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
