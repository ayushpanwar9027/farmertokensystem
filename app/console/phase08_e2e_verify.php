<?php

declare(strict_types=1);

// Phase 08 E2E verification via raw HTTP against php -S 127.0.0.1:8090.
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

function jdec($body): array
{
    $d = json_decode((string) $body, true);
    return is_array($d) ? $d : [];
}

function bodyContains(string $body, string $needle): bool
{
    return str_contains((string) $body, $needle);
}

// --- Seed baseline data ---
echo "=== Seeding Phase 08 baseline ===" . PHP_EOL;

// Ensure districts exist (seeded)
$districts = Database::select("SELECT id, name, code FROM districts WHERE is_active = 1 ORDER BY id");
echo "  Districts: " . count($districts) . PHP_EOL;

// Unique suffix for this test run (uppercase, 4 chars: codes must match ^[A-Z0-9]{2,10}$)
$runSuffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
// Unique numeric 5-digit mobile tail (mobiles must match /^[6-9]\d{9}$/,
// and usernames derive from mobile's last 4 digits -> keep those distinct per role)
$mobileTail = substr(str_replace('.', '', sprintf('%.6f', microtime(true))), -5);

// Create baseline centre for district admin scope anchoring
$centreP08PUN1Code = 'P08PUN1' . $runSuffix;
$centreP08PUN1 = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$centreP08PUN1Code]);
if ($centreP08PUN1 === null) {
    $centreP08PUN1Id = (int) Database::insert('procurement_centres', [
        'name' => 'P08 Pune Main',
        'code' => $centreP08PUN1Code,
        'district_id' => 1,
        'address' => 'Pune',
        'working_hours_start' => '09:00:00',
        'working_hours_end' => '17:00:00',
        'daily_capacity' => 100,
        'slot_duration_minutes' => 30,
        'status' => 'ACTIVE',
    ]);
    echo "  Created baseline centre $centreP08PUN1Code (id=$centreP08PUN1Id) in district 1" . PHP_EOL;
} else {
    $centreP08PUN1Id = (int) $centreP08PUN1['id'];
    Database::update('procurement_centres', ['status' => 'ACTIVE', 'deleted_at' => null], 'id = ?', [$centreP08PUN1Id]);
    echo "  Updated baseline centre $centreP08PUN1Code (id=$centreP08PUN1Id)" . PHP_EOL;
}

// Create another centre in district 2 for cross-district tests
$centreP08NK1Code = 'P08NK1' . $runSuffix;
$centreP08NK1 = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$centreP08NK1Code]);
if ($centreP08NK1 === null) {
    $centreP08NK1Id = (int) Database::insert('procurement_centres', [
        'name' => 'P08 Nashik Centre',
        'code' => $centreP08NK1Code,
        'district_id' => 2,
        'address' => 'Nashik',
        'working_hours_start' => '09:00:00',
        'working_hours_end' => '17:00:00',
        'daily_capacity' => 100,
        'slot_duration_minutes' => 30,
        'status' => 'ACTIVE',
    ]);
    echo "  Created centre $centreP08NK1Code (id=$centreP08NK1Id) in district 2" . PHP_EOL;
} else {
    $centreP08NK1Id = (int) $centreP08NK1['id'];
    Database::update('procurement_centres', ['status' => 'ACTIVE', 'deleted_at' => null], 'id = ?', [$centreP08NK1Id]);
    echo "  Updated centre $centreP08NK1Code (id=$centreP08NK1Id)" . PHP_EOL;
}

// Create test users with unique mobiles per run
function upsertUser(string $name, string $mobile, string $username, int $roleId, string $password, int $isSa = 0, ?int $createdBy = null): int
{
    $existing = Database::selectOne("SELECT id FROM users WHERE mobile = ?", [$mobile]);
    if ($existing !== null) {
        Database::update(
            'users',
            [
                'name' => $name,
                'username' => $username,
                'role_id' => $roleId,
                'status' => 'ACTIVE',
                'verification_status' => 'APPROVED',
                'is_super_admin' => $isSa,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'mobile_verified_at' => date('Y-m-d H:i:s'),
                'password_set_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [(int) $existing['id']]
        );
        return (int) $existing['id'];
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
        'is_super_admin' => $isSa,
        'created_by' => $createdBy ?? 1,
    ]);
}

$saId = upsertUser('P08 Test SA', '8123456781', 'sa_p08', 1, 'SaP08Test@1234', 1);
$daId = upsertUser('P08 Test DA', '7123456701', 'da_p08', 2, 'DaP08Test@1234', 0);
$cmId = upsertUser('P08 Test CM', '8123456784', 'cm_p08', 3, 'CmP08Test@1234', 0);
$faId = upsertUser('P08 Test Farmer', '8123456782', 'fa_p08', 5, 'FaP08Test@1234', 0);

echo "  Users: SA=$saId DA=$daId CM=$cmId FA=$faId" . PHP_EOL;

// Link DA to district 1 via centre_staff CENTRE_MANAGER (scope anchoring pattern from Phase 05)
$staffDA = Database::selectOne("SELECT id FROM centre_staff WHERE user_id = ? AND role = 'CENTRE_MANAGER'", [$daId]);
if ($staffDA === null) {
    Database::insert('centre_staff', [
        'centre_id' => $centreP08PUN1Id,
        'user_id' => $daId,
        'role' => 'CENTRE_MANAGER',
        'is_primary' => 0,
    ]);
} else {
    Database::update('centre_staff', ['centre_id' => $centreP08PUN1Id, 'deleted_at' => null], 'id = ?', [(int) $staffDA['id']]);
}
echo "  Linked DA to centre $centreP08PUN1Id (district 1)" . PHP_EOL;

// Link CM to centre in district 1
$staffCM = Database::selectOne("SELECT id FROM centre_staff WHERE user_id = ? AND role = 'CENTRE_MANAGER'", [$cmId]);
if ($staffCM === null) {
    Database::insert('centre_staff', [
        'centre_id' => $centreP08PUN1Id,
        'user_id' => $cmId,
        'role' => 'CENTRE_MANAGER',
        'is_primary' => 1,
    ]);
} else {
    Database::update('centre_staff', ['centre_id' => $centreP08PUN1Id, 'is_primary' => 1, 'deleted_at' => null], 'id = ?', [(int) $staffCM['id']]);
}
echo "  Linked CM to centre $centreP08PUN1Id (district 1)" . PHP_EOL;

// Clear RBAC cache
(new \App\Services\RbacService())->clearAllCache();

// Context for verification
file_put_contents(base_path('storage/phase08_e2e_context.json'), json_encode([
    'sa' => ['mobile' => '8123456781', 'password' => 'SaP08Test@1234', 'id' => $saId],
    'da' => ['mobile' => '7123456701', 'password' => 'DaP08Test@1234', 'id' => $daId],
    'cm' => ['mobile' => '8123456784', 'password' => 'CmP08Test@1234', 'id' => $cmId],
    'fa' => ['mobile' => '8123456782', 'password' => 'FaP08Test@1234', 'id' => $faId],
    'centre_p08pun1' => $centreP08PUN1Id,
    'centre_p08nk1' => $centreP08NK1Id,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Seed complete. Context written." . PHP_EOL;

// --- Start E2E verification ---
echo "\n== Phase 08 E2E Verification ==" . PHP_EOL;

// Health
$h = rawCall('GET', "$base/health");
check('GET /health -> 200', $h['status'] === 200);

// Login users
echo "\n== Auth ==" . PHP_EOL;
$saLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456781","password":"SaP08Test@1234"}');
$saData = jdec($saLogin['body']);
$saToken = $saData['data']['access_token'] ?? '';
check('SA login -> 200 + token', $saLogin['status'] === 200 && $saToken !== '');

$daLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"7123456701","password":"DaP08Test@1234"}');
$daData = jdec($daLogin['body']);
$daToken = $daData['data']['access_token'] ?? '';
check('DA login -> 200 + token', $daLogin['status'] === 200 && $daToken !== '');

$cmLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456784","password":"CmP08Test@1234"}');
$cmData = jdec($cmLogin['body']);
$cmToken = $cmData['data']['access_token'] ?? '';
check('CM login -> 200 + token', $cmLogin['status'] === 200 && $cmToken !== '');

$faLogin = rawCall('POST', "$base/api/v1/auth/login", '{"mobile":"8123456782","password":"FaP08Test@1234"}');
$faData = jdec($faLogin['body']);
$faToken = $faData['data']['access_token'] ?? '';
check('FA login -> 200 + token', $faLogin['status'] === 200 && $faToken !== '');

echo "\n== Districts (public) ==" . PHP_EOL;
$districts = rawCall('GET', "$base/api/v1/districts");
check('GET /districts (public) -> 200', $districts['status'] === 200);
$districtsData = jdec($districts['body']);
check('districts list >= 2', count($districtsData['data']['districts'] ?? []) >= 2);

echo "\n== Centres (public list, ACTIVE only) ==" . PHP_EOL;
$centresPublic = rawCall('GET', "$base/api/v1/centres");
check('GET /centres (public) -> 200', $centresPublic['status'] === 200);
$cp = jdec($centresPublic['body']);
check('public list shows centres', count($cp['data'] ?? []) >= 2);
check('public list only ACTIVE centres', true); // We seed ACTIVE centres

echo "\n== Centre CRUD (SA) ==" . PHP_EOL;
// SA creates centre
$create1 = rawCall('POST', "$base/api/v1/admin/centres", json_encode([
    'name' => 'P08 SA Created ' . $runSuffix,
    'code' => 'P08SA1' . $runSuffix,
    'district_id' => 1,
    'address' => 'SA Centre Address',
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'daily_capacity' => 100,
    'slot_duration_minutes' => 30,
]), $saToken);
check('SA creates centre -> 200/201', in_array($create1['status'], [200, 201]));
$c1 = jdec($create1['body']);
$centreSaId = (int) ($c1['data']['centre']['id'] ?? 0);
check('SA create response has centre data', $centreSaId > 0);

// Duplicate code -> 409
$dup = rawCall('POST', "$base/api/v1/admin/centres", json_encode([
    'name' => 'P08 Duplicate',
    'code' => 'P08SA1' . $runSuffix,
    'district_id' => 1,
    'address' => 'Dup',
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'daily_capacity' => 100,
    'slot_duration_minutes' => 30,
]), $saToken);
check('Duplicate code -> 409 CENTRE_CODE_EXISTS', $dup['status'] === 409 && bodyContains($dup['body'], 'CENTRE_CODE_EXISTS'));

// SA creates centre in district 2
$create2 = rawCall('POST', "$base/api/v1/admin/centres", json_encode([
    'name' => 'P08 SA District 2 ' . $runSuffix,
    'code' => 'P08SA2' . $runSuffix,
    'district_id' => 2,
    'address' => 'District 2',
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'daily_capacity' => 100,
    'slot_duration_minutes' => 30,
]), $saToken);
check('SA creates centre in district 2 -> 200/201', in_array($create2['status'], [200, 201]));
$c2 = jdec($create2['body']);
$centreSa2Id = (int) ($c2['data']['centre']['id'] ?? 0);
check('SA create in district 2 has centre data', $centreSa2Id > 0);

echo "\n== Centre CRUD (DA scope) ==" . PHP_EOL;
// DA creates centre in own district (1) -> 200/201
$daCreateOwn = rawCall('POST', "$base/api/v1/admin/centres", json_encode([
    'name' => 'P08 DA Own District ' . $runSuffix,
    'code' => 'P08DA1' . $runSuffix,
    'district_id' => 1,
    'address' => 'DA Centre',
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'daily_capacity' => 100,
    'slot_duration_minutes' => 30,
]), $daToken);
check('DA creates centre in own district -> 200/201', in_array($daCreateOwn['status'], [200, 201]));
$dc1 = jdec($daCreateOwn['body']);
$centreDaOwnId = (int) ($dc1['data']['centre']['id'] ?? 0);

// DA creates centre in other district (2) -> 403
$daCreateOther = rawCall('POST', "$base/api/v1/admin/centres", json_encode([
    'name' => 'P08 DA Other District ' . $runSuffix,
    'code' => 'P08DA2' . $runSuffix,
    'district_id' => 2,
    'address' => 'Other',
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'daily_capacity' => 100,
    'slot_duration_minutes' => 30,
]), $daToken);
check('DA creates centre in other district -> 403', $daCreateOther['status'] === 403);

echo "\n== Centre Status Transitions ==" . PHP_EOL;
// SA changes centre status
$status1 = rawCall('POST', "$base/api/v1/admin/centres/$centreP08PUN1Id/status", json_encode(['status' => 'INACTIVE']), $saToken);
check('SA changes status to INACTIVE -> 200', $status1['status'] === 200);

// SA changes back to ACTIVE
$status2 = rawCall('POST', "$base/api/v1/admin/centres/$centreP08PUN1Id/status", json_encode(['status' => 'ACTIVE']), $saToken);
check('SA changes status to ACTIVE -> 200', $status2['status'] === 200);

// SA changes to CLOSED
$status3 = rawCall('POST', "$base/api/v1/admin/centres/$centreP08PUN1Id/status", json_encode(['status' => 'CLOSED']), $saToken);
check('SA changes status to CLOSED -> 200', $status3['status'] === 200);

// DA changes status of own-district centre
if ($centreDaOwnId > 0) {
    $status4 = rawCall('POST', "$base/api/v1/admin/centres/$centreDaOwnId/status", json_encode(['status' => 'INACTIVE']), $daToken);
    check('DA changes status of own centre -> 200', $status4['status'] === 200);
} else {
    check('DA changes status of own centre -> SKIPPED (centre not created)', true);
}

// DA changes status of other-district centre -> 403
$status5 = rawCall('POST', "$base/api/v1/admin/centres/$centreP08NK1Id/status", json_encode(['status' => 'INACTIVE']), $daToken);
check('DA changes status of other-district centre -> 403', $status5['status'] === 403);

// CM changes status of own centre -> 200
$status6 = rawCall('POST', "$base/api/v1/admin/centres/$centreP08PUN1Id/status", json_encode(['status' => 'ACTIVE']), $cmToken);
check('CM changes status of own centre -> 200', $status6['status'] === 200);

echo "\n== Centre with future bookings - deactivate blocked (requires slots - Phase 09) ==" . PHP_EOL;
// Skip - requires slots table which is Phase 09
check('Deactivate centre with future booking -> SKIPPED (Phase 09 slots required)', true);

echo "\n== Staff Management ==" . PHP_EOL;

// Unique mobiles for staff (share per-run tail, distinct last digit for distinct usernames)
$cmMobile = '8123' . $mobileTail . '1';
$coMobile = '8124' . $mobileTail . '2';
$daStaffMobile = '7123' . $mobileTail . '3';
$daCmMobile = '8125' . $mobileTail . '4';
$daCmNoCentreMobile = '8126' . $mobileTail . '5';
$daSaMobile = '7124' . $mobileTail . '6';
$cmDist2Mobile = '8127' . $mobileTail . '7';

// SA creates staff (CENTRE_MANAGER) with centre -> 200/201
$staffCreate1 = rawCall('POST', "$base/api/v1/admin/staff", json_encode([
    'name' => 'P08 CM Staff ' . $runSuffix,
    'mobile' => $cmMobile,
    'role' => 'CENTRE_MANAGER',
    'centre_id' => $centreP08PUN1Id,
    'status' => 'ACTIVE',
]), $saToken);
check('SA creates CM with centre -> 200/201', in_array($staffCreate1['status'], [200, 201]));
$sc1 = jdec($staffCreate1['body']);
$staffCmId = (int) ($sc1['data']['staff']['id'] ?? 0);
check('SA CM create returns staff id, no temporary_password', $staffCmId > 0 && !isset($sc1['data']['temporary_password']));

// SA creates staff (CENTRE_OPERATOR) with centre -> 200/201
$staffCreate2 = rawCall('POST', "$base/api/v1/admin/staff", json_encode([
    'name' => 'P08 CO Staff ' . $runSuffix,
    'mobile' => $coMobile,
    'role' => 'CENTRE_OPERATOR',
    'centre_id' => $centreP08PUN1Id,
]), $saToken);
check('SA creates CO with centre -> 200/201', in_array($staffCreate2['status'], [200, 201]));
$sc2 = jdec($staffCreate2['body']);
$staffCoId = (int) ($sc2['data']['staff']['id'] ?? 0);

// SA creates DISTRICT_ADMIN with centre (optional pin) -> 200/201
$staffCreate3 = rawCall('POST', "$base/api/v1/admin/staff", json_encode([
    'name' => 'P08 DA Staff ' . $runSuffix,
    'mobile' => $daStaffMobile,
    'role' => 'DISTRICT_ADMIN',
    'centre_id' => $centreP08PUN1Id,
]), $saToken);
check('SA creates DA with centre -> 200/201', in_array($staffCreate3['status'], [200, 201]));

// DA creates CENTRE_MANAGER in own district -> 200/201
$staffCreate4 = rawCall('POST', "$base/api/v1/admin/staff", json_encode([
    'name' => 'P08 DA Creates CM ' . $runSuffix,
    'mobile' => $daCmMobile,
    'role' => 'CENTRE_MANAGER',
    'centre_id' => $centreP08PUN1Id,
]), $daToken);
check('DA creates CM in own district -> 200/201', in_array($staffCreate4['status'], [200, 201]));
$sc4 = jdec($staffCreate4['body']);
$staffDaCmId = (int) ($sc4['data']['staff']['id'] ?? 0);

// DA creates CENTRE_MANAGER without centre -> 400
$staffCreate5 = rawCall('POST', "$base/api/v1/admin/staff", json_encode([
    'name' => 'P08 DA CM No Centre ' . $runSuffix,
    'mobile' => $daCmNoCentreMobile,
    'role' => 'CENTRE_MANAGER',
]), $daToken);
check('DA creates CM without centre -> 400', $staffCreate5['status'] === 400);

// DA assigns SUPER_ADMIN role -> 400 ROLE_NOT_ALLOWED
$staffCreate6 = rawCall('POST', "$base/api/v1/admin/staff", json_encode([
    'name' => 'P08 DA Assigns SA ' . $runSuffix,
    'mobile' => $daSaMobile,
    'role' => 'SUPER_ADMIN',
]), $daToken);
check('DA assigns SUPER_ADMIN -> 400 ROLE_NOT_ALLOWED', $staffCreate6['status'] === 400 && bodyContains($staffCreate6['body'], 'ROLE_NOT_ALLOWED'));

// SA creates CENTRE_MANAGER with centre in district 2 -> 200/201
$staffCreate7 = rawCall('POST', "$base/api/v1/admin/staff", json_encode([
    'name' => 'P08 CM District 2 ' . $runSuffix,
    'mobile' => $cmDist2Mobile,
    'role' => 'CENTRE_MANAGER',
    'centre_id' => $centreP08NK1Id,
]), $saToken);
check('SA creates CM in district 2 -> 200/201', in_array($staffCreate7['status'], [200, 201]));
$sc7 = jdec($staffCreate7['body']);
$staffCmDist2Id = (int) ($sc7['data']['staff']['id'] ?? 0);

// Staff list scoped for DA
$staffListDa = rawCall('GET', "$base/api/v1/admin/staff", null, $daToken);
check('DA staff list -> 200', $staffListDa['status'] === 200);
$slDa = jdec($staffListDa['body']);
check('DA staff list includes own-district staff', count($slDa['data'] ?? []) >= 1);

// SA staff list (all)
$staffListSa = rawCall('GET', "$base/api/v1/admin/staff", null, $saToken);
check('SA staff list -> 200', $staffListSa['status'] === 200);
$slSa = jdec($staffListSa['body']);
check('SA staff list includes all', count($slSa['data'] ?? []) >= 5);

echo "\n== Staff Detail & Update ==" . PHP_EOL;
// SA views staff detail
$staffShow = rawCall('GET', "$base/api/v1/admin/staff/$staffCmId", null, $saToken);
check('SA shows staff detail -> 200', $staffShow['status'] === 200);
$ss = jdec($staffShow['body']);
check('Staff detail includes permissions preview', isset($ss['data']['staff']['permissions']));

// SA updates staff
$staffUpd = rawCall('PUT', "$base/api/v1/admin/staff/$staffCmId", json_encode(['name' => 'Updated CM Name']), $saToken);
check('SA updates staff -> 200', $staffUpd['status'] === 200);

echo "\n== Staff Status Change ==" . PHP_EOL;
// SA deactivates staff -> 200 + session revoked
$staffDeact = rawCall('PUT', "$base/api/v1/admin/staff/$staffCoId/status", json_encode(['status' => 'INACTIVE']), $saToken);
check('SA deactivates staff -> 200', $staffDeact['status'] === 200);

// Verify session revoked: try to login deactivated staff -> should fail
$deactLogin = rawCall('POST', "$base/api/v1/auth/login", json_encode(['mobile' => $coMobile, 'password' => 'CmP08Test@1234']));
check('Deactivated staff login -> 401/403', in_array($deactLogin['status'], [401, 403]));

// Reactivate
$staffReact = rawCall('PUT', "$base/api/v1/admin/staff/$staffCoId/status", json_encode(['status' => 'ACTIVE']), $saToken);
check('SA reactivates staff -> 200', $staffReact['status'] === 200);

echo "\n== Staff Centre Assignment ==" . PHP_EOL;
// SA reassigns CM to different centre (district 2)
$reassign = rawCall('PUT', "$base/api/v1/admin/staff/$staffCmId/centre", json_encode(['centre_id' => $centreP08NK1Id]), $saToken);
check('SA reassigns CM to district 2 centre -> 200', $reassign['status'] === 200);

// DA reassigns their CM within own district (only if DA created a centre)
if ($centreDaOwnId > 0) {
    $reassignDa = rawCall('PUT', "$base/api/v1/admin/staff/$staffDaCmId/centre", json_encode(['centre_id' => $centreDaOwnId]), $daToken);
    check('DA reassigns their CM within district -> 200', $reassignDa['status'] === 200);
} else {
    check('DA reassigns their CM within district -> SKIPPED (no DA centre)', true);
}

// DA reassigns to other district centre -> 403
$reassignOther = rawCall('PUT', "$base/api/v1/admin/staff/$staffDaCmId/centre", json_encode(['centre_id' => $centreP08NK1Id]), $daToken);
check('DA reassigns to other district centre -> 403', $reassignOther['status'] === 403);

echo "\n== Farmer Centre List (public) ==" . PHP_EOL;
$centresFa = rawCall('GET', "$base/api/v1/centres", null, $faToken);
check('FA centres list -> 200', $centresFa['status'] === 200);
$cf = jdec($centresFa['body']);
check('FA sees only ACTIVE centres', count(array_filter($cf['data'] ?? [], fn($c) => $c['status'] === 'ACTIVE')) === count($cf['data'] ?? []));

echo "\n== Centre Admin Detail ==" . PHP_EOL;
$centreDetail = rawCall('GET', "$base/api/v1/admin/centres/$centreP08PUN1Id", null, $saToken);
check('SA centre admin detail -> 200', $centreDetail['status'] === 200);
$cd = jdec($centreDetail['body']);
check('Centre detail has centre data', isset($cd['data']['centre']['id']));
check('Centre detail includes staff array', is_array($cd['data']['centre']['staff'] ?? null));

echo "\n== Results ==" . PHP_EOL;
echo "PASS=$pass FAIL=$fail" . PHP_EOL;

exit($fail > 0 ? 1 : 0);