<?php

declare(strict_types=1);

// Phase 11 E2E verification via raw HTTP against php -S 127.0.0.1:8090.
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

function spawnProbe(string $token, int $centreId, string $date): string
{
    global $base, $basePath;
    $pipes = [];
    $proc = proc_open(
        [PHP_BINARY, $basePath . '/app/console/phase11_callnext_probe.php', $base, $token, (string) $centreId, $date],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $basePath
    );
    if (!is_resource($proc)) {
        return '{}';
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return $stdout !== '' ? $stdout : $stderr;
}

function runCron(string $job): string
{
    global $basePath;
    $pipes = [];
    $proc = proc_open(
        [PHP_BINARY, $basePath . '/app/console/cron.php', '--job=' . $job],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $basePath
    );
    if (!is_resource($proc)) {
        return '';
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return trim($stdout . ' ' . $stderr);
}

// --- Prepare baseline data ---
echo "=== Phase 11 baseline setup ===" . PHP_EOL;

$suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$c1Code = 'P11A' . $suffix;
$c1 = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$c1Code]);
if ($c1 === null) {
    $c1Id = (int) Database::insert('procurement_centres', [
        'name' => 'P11 Queue Centre ' . $suffix,
        'code' => $c1Code,
        'district_id' => 1,
        'address' => 'Phase 11 Queue',
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

$c2Code = 'P11B' . $suffix;
$c2 = Database::selectOne("SELECT id FROM procurement_centres WHERE code = ?", [$c2Code]);
if ($c2 === null) {
    $c2Id = (int) Database::insert('procurement_centres', [
        'name' => 'P11 Other Centre ' . $suffix,
        'code' => $c2Code,
        'district_id' => 1,
        'address' => 'Phase 11 Other',
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
    Database::getConnection()->prepare("DELETE FROM queue_entries WHERE centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE t FROM tokens t INNER JOIN bookings b ON b.id = t.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE bc FROM booking_crops bc INNER JOIN bookings b ON b.id = bc.booking_id WHERE b.centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM bookings WHERE centre_id = ?")->execute([$centreId]);
    Database::getConnection()->prepare("DELETE FROM slots WHERE centre_id = ?")->execute([$centreId]);
}

$crop1 = (int) (Database::selectOne("SELECT id FROM crops WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1"))['id'];

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
         VALUES (?, 'P11 Village', 1, 'Maharashtra', '411001', 'APPROVED', NOW())
         ON DUPLICATE KEY UPDATE verification_status = 'APPROVED'"
    )->execute([$userId]);
}

setSetting('booking.horizon_days', '7');
setSetting('booking.min_lead_hours', '0');
setSetting('booking.cancel_lead_minutes', '120');
setSetting('booking.max_active', '1');
setSetting('auto_confirm_on_payment', '1');
setSetting('queue.avg_minutes_per_token', '10');
setSetting('queue.grace_no_show_minutes', '5');
setSetting('queue.recall_limit', '1');
setSetting('queue.notify_threshold', '3');
(new \App\Services\RbacService())->clearAllCache();

$passWord = 'FaP11Test@1234';
$farmerIds = [];
$farmerUsers = [];
$farmerMobiles = [];
for ($i = 1; $i <= 5; $i++) {
    $mobile = '9171' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT) . $i;
    $farmerIds[$i] = createTestUser('fa_p11_' . $i . '_' . $suffix, 'P11 Farmer ' . $i, 5, $mobile, $passWord);
    createFarmerRow($farmerIds[$i]);
    $farmerUsers[$i] = $farmerIds[$i];
    $farmerMobiles[$i] = $mobile;
}

$opMobile = '9172' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$op2Mobile = '9173' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$opId = createTestUser('op_p11_' . $suffix, 'P11 Operator', 4, $opMobile, 'OpP11Test@1234');
$op2Id = createTestUser('op2_p11_' . $suffix, 'P11 Other Operator', 4, $op2Mobile, 'OpP11Test@1234');

Database::getConnection()->prepare(
    "INSERT INTO centre_staff (user_id, centre_id, role)
     VALUES (?, ?, 'CENTRE_OPERATOR')
     ON DUPLICATE KEY UPDATE centre_id = VALUES(centre_id), deleted_at = NULL"
)->execute([$opId, $c1Id]);
Database::getConnection()->prepare(
    "INSERT INTO centre_staff (user_id, centre_id, role)
     VALUES (?, ?, 'CENTRE_OPERATOR')
     ON DUPLICATE KEY UPDATE centre_id = VALUES(centre_id), deleted_at = NULL"
)->execute([$op2Id, $c2Id]);

echo "  Centre1=$c1Id ($c1Code)  Centre2=$c2Id ($c2Code)  Slot1=$slot1  Slot2=$slot2" . PHP_EOL;
echo "  Farmers: " . implode(',', $farmerIds) . "  Operator=$opId  OtherOperator=$op2Id" . PHP_EOL;

// --- Login all participants ---
function login(string $mobile, string $password): string
{
    global $base;
    $res = rawCall('POST', "$base/api/v1/auth/login", json_encode(['mobile' => $mobile, 'password' => $password]));
    $data = jdec($res['body']);
    return (string) ($data['data']['access_token'] ?? '');
}

$opToken = login($opMobile, 'OpP11Test@1234');
$op2Token = login($op2Mobile, 'OpP11Test@1234');
$farmerTokens = [];
for ($i = 1; $i <= 5; $i++) {
    $farmerTokens[$i] = login($farmerMobiles[$i], $passWord);
}

// --- Create 5 CONFIRMED bookings (each farmer, same centre + day) ---
echo "\n== Bookings enqueue (5 farmers) ==" . PHP_EOL;
$tokens = [];
$bookingIds = [];
for ($i = 1; $i <= 5; $i++) {
    $res = rawCall('POST', "$base/api/v1/bookings", json_encode([
        'booking_date' => $tomorrow,
        'slot_id' => $slot1,
        'centre_id' => $c1Id,
        'crops' => [['crop_id' => $crop1, 'qty' => 20 + $i]],
    ]), $farmerTokens[$i]);
    $d = jdec($res['body']);
    $bk = $d['data']['booking'] ?? [];
    $bookingIds[$i] = (int) ($bk['id'] ?? 0);
    $tokens[$i] = (string) ($bk['token']['token_number'] ?? '');
    check("booking $i CONFIRMED + token", $res['status'] === 201 && ($bk['status'] ?? '') === 'CONFIRMED' && $tokens[$i] !== '');
}

$qrows = Database::select(
    "SELECT qe.*, t.token_number FROM queue_entries qe
     LEFT JOIN tokens t ON t.booking_id = qe.booking_id
     WHERE qe.centre_id = ? AND qe.date = ?
     ORDER BY qe.position ASC, qe.id ASC",
    [$c1Id, $tomorrow]
);
check('queue has 5 entries', count($qrows) === 5);
$posOk = true;
foreach ($qrows as $i => $q) {
    if ((string) $q['status'] !== 'WAITING' || (int) $q['position'] !== $i + 1 || ($q['token_number'] ?? '') !== $tokens[$i + 1]) {
        $posOk = false;
    }
}
check('positions 1..5 in booking order', $posOk);
$entryIds = [];
foreach ($qrows as $i => $q) {
    $entryIds[$i + 1] = (int) $q['id'];
}

// --- Farmer self-service queue lookups ---
echo "\n== /queue/my ==" . PHP_EOL;
$myRes = rawCall('GET', "$base/api/v1/queue/my", null, $farmerTokens[1]);
$myq = jdec($myRes['body'])['data']['queue'] ?? [];
check('GET /queue/my -> 200', $myRes['status'] === 200);
check('my queue: position 1, ahead 0, WAITING', ($myq['token'] ?? '') === $tokens[1] && (int) ($myq['position'] ?? -1) === 1 && (int) ($myq['ahead'] ?? -1) === 0 && ($myq['status'] ?? '') === 'WAITING');

echo "\n== /queue/live (no PII) ==" . PHP_EOL;
$liveRes = rawCall('GET', "$base/api/v1/queue/live?centre_id=$c1Id&date=$tomorrow", null, $farmerTokens[1]);
$live = jdec($liveRes['body'])['data']['live'] ?? [];
check('GET /queue/live -> 200', $liveRes['status'] === 200);
check('live queue_size 5', (int) ($live['queue_size'] ?? 0) === 5);
check('live has no farmer PII', !str_contains($liveRes['body'], 'P11 Farmer') && !str_contains($liveRes['body'], $farmerMobiles[1]));

// --- Atomic parallel call-next ---
echo "\n== Parallel call-next (exactly-once) ==" . PHP_EOL;
$outs = [];
$outs[] = spawnProbe($opToken, $c1Id, $tomorrow);
$outs[] = spawnProbe($opToken, $c1Id, $tomorrow);
$wins = [];
$losers = 0;
foreach ($outs as $out) {
    $p = jdec($out);
    if ((int) ($p['status'] ?? 0) === 200 && ($p['token'] ?? '') !== '') {
        $wins[] = (string) $p['token'];
    } else {
        $losers++;
    }
}
check('parallel probes: exactly one winner', count($wins) === 1 && $losers === 1);
check('parallel winner is first position (token1)', ($wins[0] ?? '') === $tokens[1]);

// --- Operator queue list + skip ---
echo "\n== Operator queue + skip ==" . PHP_EOL;
$listRes = rawCall('GET', "$base/api/v1/operator/queue?centre_id=$c1Id&date=$tomorrow", null, $opToken);
$listData = jdec($listRes['body']);
$entries = $listData['data']['entries'] ?? [];
check('operator list 200 with 5 entries', $listRes['status'] === 200 && count($entries) === 5);

$calledEntryId = null;
foreach ($entries as $e) {
    if (($e['status'] ?? '') === 'CALLED' && ($e['token'] ?? '') === $tokens[1]) {
        $calledEntryId = (int) $e['id'];
    }
}
check('list shows token1 as CALLED', $calledEntryId !== null);

$skipRes = rawCall('POST', "$base/api/v1/operator/queue/{$calledEntryId}/skip", json_encode(['reason' => 'farmer unavailable']), $opToken);
$skipData = jdec($skipRes['body'])['data']['entry'] ?? [];
check('skip -> 200 SKIPPED recall_eligible', $skipRes['status'] === 200 && ($skipData['status'] ?? '') === 'SKIPPED' && ($skipData['recall_eligible'] ?? false) === true);

$cnRes = rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c1Id, 'date' => $tomorrow]), $opToken);
$cnData = jdec($cnRes['body'])['data']['entry'] ?? [];
check('call-next -> token2', ($cnData['token'] ?? '') === $tokens[2]);
$cn2EntryId = (int) ($cnData['entry_id'] ?? 0);

// --- Recall ---
echo "\n== Recall ==" . PHP_EOL;
$rcRes = rawCall('POST', "$base/api/v1/operator/queue/{$calledEntryId}/recall", json_encode([]), $opToken);
$rcData = jdec($rcRes['body'])['data']['entry'] ?? [];
check('recall skipped entry -> CALLED at end', $rcRes['status'] === 200 && ($rcData['status'] ?? '') === 'CALLED' && (int) ($rcData['position'] ?? 0) === 5);
usleep(2100000);
$rc2Res = rawCall('POST', "$base/api/v1/operator/queue/{$calledEntryId}/recall", json_encode([]), $opToken);
check('recall non-skipped -> 409 ENTRY_STATUS_INVALID', $rc2Res['status'] === 409 && bodyContains($rc2Res['body'], 'ENTRY_STATUS_INVALID'));

// --- No-show grace ---
echo "\n== No-show (grace window) ==" . PHP_EOL;
$nsRes = rawCall('POST', "$base/api/v1/operator/queue/{$cn2EntryId}/no-show", json_encode([]), $opToken);
check('no-show pre-grace -> 409 ENTRY_STATUS_INVALID', $nsRes['status'] === 409 && bodyContains($nsRes['body'], 'ENTRY_STATUS_INVALID'));
setSetting('queue.grace_no_show_minutes', '0');
$ns2Res = rawCall('POST', "$base/api/v1/operator/queue/{$cn2EntryId}/no-show", json_encode(['notes' => 'absent']), $opToken);
$ns2Data = jdec($ns2Res['body'])['data']['entry'] ?? [];
check('no-show after grace 0 -> NO_SHOW', $ns2Res['status'] === 200 && ($ns2Data['status'] ?? '') === 'NO_SHOW');
setSetting('queue.grace_no_show_minutes', '5');

$cn3Res = rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c1Id, 'date' => $tomorrow]), $opToken);
$cn3Data = jdec($cn3Res['body'])['data']['entry'] ?? [];
check('call-next -> token3', ($cn3Data['token'] ?? '') === $tokens[3]);
$cn3EntryId = (int) ($cn3Data['entry_id'] ?? 0);

// --- Farmer ETA (position * avg_minutes) ---
echo "\n== Farmer ETA ==" . PHP_EOL;
$pos4row = Database::selectOne("SELECT position FROM queue_entries WHERE booking_id = ?", [$bookingIds[4]]);
$pos4 = (int) $pos4row['position'];
$ahead4row = Database::selectOne(
    "SELECT COUNT(*) AS c FROM queue_entries
     WHERE centre_id = ? AND date = ? AND status IN ('WAITING','CALLED','IN_PROGRESS') AND position < ?",
    [$c1Id, $tomorrow, $pos4]
);
$expectedAhead = (int) $ahead4row['c'];
$my4 = jdec(rawCall('GET', "$base/api/v1/queue/my", null, $farmerTokens[4])['body'])['data']['queue'] ?? [];
check('farmer4 my-queue position/ahead', ($my4['token'] ?? '') === $tokens[4] && (int) ($my4['position'] ?? 0) === $pos4 && (int) ($my4['ahead'] ?? -1) === $expectedAhead);
check('farmer4 eta = ahead * avg_minutes', (int) ($my4['eta_minutes'] ?? -1) === $expectedAhead * 10);

// --- Scope enforcement (other centre) ---
echo "\n== Centre scope enforcement ==" . PHP_EOL;
$forbidden = rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c1Id, 'date' => $tomorrow]), $op2Token);
check('other-centre call-next -> 403 SCOPE_DENIED', $forbidden['status'] === 403 && bodyContains($forbidden['body'], 'SCOPE_DENIED'));
$forbidden2 = rawCall('POST', "$base/api/v1/operator/queue/{$cn3EntryId}/skip", json_encode(['reason' => 'test']), $op2Token);
check('other-centre skip via entryId -> 403 SCOPE_DENIED', $forbidden2['status'] === 403 && bodyContains($forbidden2['body'], 'SCOPE_DENIED'));

$live2 = jdec(rawCall('GET', "$base/api/v1/queue/live?centre_id=$c1Id&date=$tomorrow", null, $opToken)['body'])['data']['live'] ?? [];
check('live queue_size 4 + no_show 1', (int) ($live2['queue_size'] ?? 0) === 4 && (int) ($live2['no_show'] ?? -1) === 1);

// --- Token status ownership ---
echo "\n== Token status (ownership) ==" . PHP_EOL;
$stRes = rawCall('GET', "$base/api/v1/queue/" . urlencode($tokens[3]) . "/status", null, $farmerTokens[3]);
$stData = jdec($stRes['body'])['data']['queue'] ?? [];
check('own token status -> CALLED', $stRes['status'] === 200 && ($stData['status'] ?? '') === 'CALLED');
$stWrong = rawCall('GET', "$base/api/v1/queue/" . urlencode($tokens[3]) . "/status", null, $farmerTokens[4]);
check('other farmer token -> 404 QUEUE_NOT_FOUND', $stWrong['status'] === 404 && bodyContains($stWrong['body'], 'QUEUE_NOT_FOUND'));

// --- Recall limit flow ---
echo "\n== Recall limit ==" . PHP_EOL;
$skip3 = jdec(rawCall('POST', "$base/api/v1/operator/queue/{$cn3EntryId}/skip", json_encode(['reason' => 'not now']), $opToken)['body'])['data']['entry'] ?? [];
check('skip farmer3 -> SKIPPED eligible', ($skip3['status'] ?? '') === 'SKIPPED' && ($skip3['recall_eligible'] ?? false) === true);
$cn4 = jdec(rawCall('POST', "$base/api/v1/operator/queue/call-next", json_encode(['centre_id' => $c1Id, 'date' => $tomorrow]), $opToken)['body'])['data']['entry'] ?? [];
check('call-next -> token4', ($cn4['token'] ?? '') === $tokens[4]);
$r3 = jdec(rawCall('POST', "$base/api/v1/operator/queue/{$cn3EntryId}/recall", json_encode([]), $opToken)['body'])['data']['entry'] ?? [];
check('recall farmer3 -> CALLED (recall_count 1)', ($r3['status'] ?? '') === 'CALLED' && (int) ($r3['recall_count'] ?? 0) === 1);
$skip3b = jdec(rawCall('POST', "$base/api/v1/operator/queue/{$cn3EntryId}/skip", json_encode(['reason' => 'again']), $opToken)['body'])['data']['entry'] ?? [];
check('re-skip farmer3 -> not eligible', ($skip3b['status'] ?? '') === 'SKIPPED' && ($skip3b['recall_eligible'] ?? true) === false);
$r3b = rawCall('POST', "$base/api/v1/operator/queue/{$cn3EntryId}/recall", json_encode([]), $opToken);
check('recall beyond limit -> 409 RECALL_LIMIT_REACHED', $r3b['status'] === 409 && bodyContains($r3b['body'], 'RECALL_LIMIT_REACHED'));

// --- Cancel -> entry cancelled + renumbered ---
echo "\n== Cancel + renumber ==" . PHP_EOL;
$cancelRes = rawCall('POST', "$base/api/v1/bookings/{$bookingIds[5]}/cancel", json_encode(['reason' => 'phase11 test']), $farmerTokens[5]);
check('farmer cancels own booking -> 200', $cancelRes['status'] === 200);
$my5 = rawCall('GET', "$base/api/v1/queue/my", null, $farmerTokens[5]);
check('cancelled booking -> 404 QUEUE_NOT_FOUND for /queue/my', $my5['status'] === 404 && bodyContains($my5['body'], 'QUEUE_NOT_FOUND'));
$c5 = Database::selectOne("SELECT status FROM queue_entries WHERE booking_id = ?", [$bookingIds[5]]);
check('queue entry of cancelled booking -> CANCELLED', ($c5['status'] ?? '') === 'CANCELLED');
$active = Database::select(
    "SELECT position FROM queue_entries
     WHERE centre_id = ? AND date = ? AND status IN ('WAITING','CALLED','IN_PROGRESS')
     ORDER BY position ASC, id ASC",
    [$c1Id, $tomorrow]
);
$contiguous = true;
foreach ($active as $i => $a) {
    if ((int) $a['position'] !== $i + 1) {
        $contiguous = false;
    }
}
check('active positions contiguous after cancel', $contiguous);

// --- Operator stats ---
echo "\n== Operator stats ==" . PHP_EOL;
$statsRes = rawCall('GET', "$base/api/v1/operator/queue/stats?centre_id=$c1Id&date=$tomorrow", null, $opToken);
$stats = jdec($statsRes['body'])['data']['stats'] ?? [];
check('stats -> 200 with fields', $statsRes['status'] === 200 && isset($stats['served_today'], $stats['waiting'], $stats['no_show'], $stats['completion_rate'], $stats['avg_wait_minutes']));

// --- Cron queue-notify (deduped) ---
echo "\n== queue-notify cron (dedup) ==" . PHP_EOL;
$cronOut = runCron('queue-notify');
$cnt1row = Database::selectOne("SELECT COUNT(*) AS c FROM notification_logs WHERE event_type = 'QUEUE_APPROACHING' AND event_ref LIKE 'queue_approach_%'");
$cnt1 = (int) $cnt1row['c'];
check('queue-notify queued approaching alerts', $cnt1 >= 1);
$cronOut2 = runCron('queue-notify');
$cnt2row = Database::selectOne("SELECT COUNT(*) AS c FROM notification_logs WHERE event_type = 'QUEUE_APPROACHING' AND event_ref LIKE 'queue_approach_%'");
check('queue-notify idempotent (no duplicates)', (int) $cnt1row['c'] === (int) $cnt2row['c']);

echo "\n=== Result: {$pass} passed, {$fail} failed ===" . PHP_EOL;
exit($fail > 0 ? 1 : 0);