<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

$seederDir = base_path('database/seeders');

$files = [
    'roles_seeder.php',
    'permissions_seeder.php',
    'role_permissions_seeder.php',
    'language_seeder.php',
    'translations_seeder.php',
    'settings_seeder.php',
    'secrets_seeder.php',
    'district_seeder.php',
    'crop_seeder.php',
    'crop_rate_seeder.php',
];

foreach ($files as $file) {
    $path = $seederDir . '/' . $file;
    if (!file_exists($path)) {
        echo "ERROR: Seeder not found: {$file}\n";
        exit(1);
    }
    require_once $path;
}

$pdo = Database::getConnection();

$argv = $argv ?? [];
$doReset = in_array('--reset', $argv);
$doDemo = in_array('--demo', $argv);

if ($doReset) {
    echo "=== Resetting demo data ===\n\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DELETE p FROM payments p
        JOIN procurements pr ON p.procurement_id = pr.id
        JOIN bookings b ON pr.booking_id = b.id
        WHERE b.user_id IN (SELECT id FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015')");
    $pdo->exec("DELETE pr FROM procurements pr
        JOIN bookings b ON pr.booking_id = b.id
        WHERE b.user_id IN (SELECT id FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015')");
    $pdo->exec("DELETE q FROM queue_entries q
        JOIN bookings b ON q.booking_id = b.id
        WHERE b.user_id IN (SELECT id FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015')");
    $pdo->exec("DELETE t FROM tokens t
        JOIN bookings b ON t.booking_id = b.id
        WHERE b.user_id IN (SELECT id FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015')");
    $pdo->exec("DELETE bc FROM booking_crops bc
        JOIN bookings b ON bc.booking_id = b.id
        WHERE b.user_id IN (SELECT id FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015')");
    $pdo->exec("DELETE FROM bookings WHERE user_id IN (SELECT id FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015')");
    $pdo->exec("DELETE FROM farmers WHERE user_id IN (SELECT id FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015')");
    $pdo->exec("DELETE FROM procurement_centres WHERE code LIKE 'CEN%'");
    $pdo->exec("DELETE FROM slots WHERE centre_id NOT IN (SELECT id FROM procurement_centres)");
    $pdo->exec("DELETE FROM users WHERE mobile BETWEEN '9000000001' AND '9000000015'");
    $pdo->exec("DELETE cs FROM centre_staff cs WHERE cs.user_id IN (SELECT id FROM users WHERE mobile IN ('7010000001','9010000001','9020000001'))");
    $pdo->exec("DELETE FROM users WHERE mobile IN ('7010000001','9010000001','9020000001')");
    $pdo->exec("DELETE FROM login_history");
    $pdo->exec("DELETE FROM rate_limit_logs");
    $pdo->exec("DELETE FROM otp_verifications");
    $pdo->exec("UPDATE users SET status = 'ACTIVE'");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "  Demo data cleared.\n\n";
}

echo "=== Farmer Procurement System — Seed Runner ===\n\n";

$totalInserted = 0;

echo "Seeding roles...\n";
$rolesCount = seedRoles($pdo);
echo "  Roles: {$rolesCount} inserted (5 expected)\n\n";
$totalInserted += $rolesCount;

echo "Seeding permissions...\n";
$permsCount = seedPermissions($pdo);
echo "  Permissions: {$permsCount} inserted (61 expected)\n\n";
$totalInserted += $permsCount;

echo "Seeding role_permissions...\n";
$rpCount = seedRolePermissions($pdo);
echo "  Role-Permission mappings: {$rpCount} inserted\n\n";
$totalInserted += $rpCount;

echo "Seeding languages...\n";
$langCount = seedLanguages($pdo);
echo "  Languages: {$langCount} inserted (2 expected)\n\n";
$totalInserted += $langCount;

echo "Seeding translations...\n";
$trCount = seedTranslations($pdo);
echo "  Translations: {$trCount} upserted (en + hi string packs)\n\n";
$totalInserted += $trCount;

echo "Seeding system_settings...\n";
$settingsCount = seedSettings($pdo);
echo "  Settings: {$settingsCount} inserted (48 expected)\n\n";
$totalInserted += $settingsCount;

echo "Seeding system_secrets...\n";
$secretsCount = seedSecrets($pdo);
echo "  Secrets: {$secretsCount} inserted (7 expected)\n\n";
$totalInserted += $secretsCount;

echo "Seeding districts...\n";
$districtCount = seedDistricts($pdo);
echo "  Districts: {$districtCount} inserted (5 expected)\n\n";
$totalInserted += $districtCount;

echo "Seeding crops...\n";
$cropCount = seedCrops($pdo);
echo "  Crops: {$cropCount} inserted\n\n";
$totalInserted += $cropCount;

echo "Seeding crop rates...\n";
$cropRateCount = seedCropRates($pdo);
echo "  Crop Rates: {$cropRateCount} inserted\n\n";
$totalInserted += $cropRateCount;

echo "=== Seed Complete ===\n";
echo "Total rows inserted: {$totalInserted}\n";
echo "(Re-running will not duplicate any data — all inserts are idempotent)\n\n";

// ──────────────────────────────────────────────────────────────
// Demo seed
// ──────────────────────────────────────────────────────────────
if ($doDemo) {
    echo "\n=== Demo Seed — SIH Showcase ===\n\n";

    $districtRows = $pdo->query("SELECT id, name FROM districts ORDER BY id")->fetchAll();
    if (count($districtRows) < 3) {
        echo "ERROR: At least 3 districts required. Run base seed first.\n";
        exit(1);
    }

    // ── 5 Procurement Centres ──
    $centres = [
        ['name' => 'Pune Central Procurement Hub',  'code' => 'CEN01', 'district' => $districtRows[0]['id']],
        ['name' => 'Pune East Procurement Centre',  'code' => 'CEN02', 'district' => $districtRows[0]['id']],
        ['name' => 'Nashik Main Centre',            'code' => 'CEN03', 'district' => $districtRows[1]['id']],
        ['name' => 'Nagpur District Centre',        'code' => 'CEN04', 'district' => $districtRows[2]['id']],
        ['name' => 'Ahmednagar Rural Centre',       'code' => 'CEN05', 'district' => $districtRows[3]['id']],
    ];

    $centreIds = [];
    $insertedCentres = 0;
    $stmtCentre = $pdo->prepare("INSERT IGNORE INTO procurement_centres
        (name, code, district_id, address, working_hours_start, working_hours_end, daily_capacity, slot_duration_minutes, status)
        VALUES (?, ?, ?, ?, '09:00:00', '17:00:00', 100, 30, 'ACTIVE')");

    foreach ($centres as $c) {
        $stmtCentre->execute([$c['name'], $c['code'], $c['district'], "Demo Address, {$c['name']}"]);
        $existing = $pdo->prepare("SELECT id FROM procurement_centres WHERE code = ?");
        $existing->execute([$c['code']]);
        $row = $existing->fetch();
        $centreIds[] = (int) $row['id'];
        if ($stmtCentre->rowCount() > 0) {
            $insertedCentres++;
        }
    }
    echo "Centres: {$insertedCentres} created\n";

    // ── ~30 Slots across next 7 days ──
    $bookingPrefix = date('Ymd') . '-';
    $today = new DateTime();
    $slotDates = [];
    for ($i = 1; $i <= 7; $i++) {
        $d = clone $today;
        $d->modify("+{$i} days");
        if ((int) $d->format('N') < 6) {
            $slotDates[] = $d->format('Y-m-d');
        }
    }

    $slotIds = [];
    $insertedSlots = 0;
    $timeSlots = [
        ['09:00:00', '10:00:00'],
        ['10:00:00', '11:00:00'],
        ['11:00:00', '12:00:00'],
        ['14:00:00', '15:00:00'],
        ['15:00:00', '16:00:00'],
    ];
    $numTimeSlots = count($timeSlots);
    $stmtSlot = $pdo->prepare("INSERT IGNORE INTO slots
        (centre_id, date, start_time, end_time, capacity, status)
        VALUES (?, ?, ?, ?, 20, 'ACTIVE')");

    $slotPerDay = [4, 4, 3, 3, 3, 2, 2];
    foreach ($slotDates as $dayIdx => $slotDate) {
        $numSlots = $slotPerDay[$dayIdx] ?? 2;
        foreach ($centreIds as $cIdx => $cid) {
            for ($s = 0; $s < $numSlots && $s < $numTimeSlots; $s++) {
                $stmtSlot->execute([$cid, $slotDate, $timeSlots[$s][0], $timeSlots[$s][1]]);
                $existing = $pdo->prepare("SELECT id FROM slots WHERE centre_id = ? AND date = ? AND start_time = ?");
                $existing->execute([$cid, $slotDate, $timeSlots[$s][0]]);
                $row = $existing->fetch();
                if ($row) {
                    $slotIds[$dayIdx][$cIdx][] = (int) $row['id'];
                    if ($stmtSlot->rowCount() > 0) {
                        $insertedSlots++;
                    }
                }
            }
        }
    }
    echo "Slots: {$insertedSlots} created (plus existing)\n";

    // ── 15 Farmers (3 with 2FA) ──
    $demoPassword = 'Demo@1234';
    $passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);
    $districtIds = [
        (int) $districtRows[0]['id'],
        (int) $districtRows[1]['id'],
        (int) $districtRows[2]['id'],
    ];
    $centreIdx = 0;
    $villages = [
        'Hadapsar', 'Kothrud', 'Viman Nagar',
        'Wanowarie', 'Bavdhan', 'Kharadi',
        'Yerwada', 'Undri', 'Fursungi',
        'Sangamwadi', 'Aundh', 'Baner',
        'Wadgaon Sheri', 'Mundhwa', 'Lonikand',
    ];

    $insertedUsers = 0;
    $userIds = [];
    for ($i = 1; $i <= 15; $i++) {
        $mobile = '90000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $existing = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $existing->execute([$mobile]);
        $row = $existing->fetch();
        if ($row) {
            $userIds[] = (int) $row['id'];
            continue;
        }

        $is2fa = in_array($i, [1, 2, 3]);
        $districtId = $districtIds[($i - 1) % 3];
        $centreForDistrict = $districtId === $districtIds[0]
            ? $centreIds[($i - 1) % 2]
            : ($districtId === $districtIds[1] ? $centreIds[2] : $centreIds[3]);

        $farmerName = 'Demo Farmer ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $centreIdx++;

        $stmtUser = $pdo->prepare("INSERT IGNORE INTO users
            (name, mobile, username, password_hash, role_id, status, verification_status,
             mobile_verified_at, password_set_at, two_factor_enabled, two_factor_enabled_at,
             is_super_admin, created_by)
            VALUES (?, ?, ?, ?, 5, 'ACTIVE', 'APPROVED', NOW(), NOW(), ?, ?, 0, 1)");
        $stmtUser->execute([
            $farmerName,
            $mobile,
            'demo_farmer_' . $i,
            $passwordHash,
            $is2fa ? 1 : 0,
            $is2fa ? date('Y-m-d H:i:s') : null,
        ]);

        $existing = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $existing->execute([$mobile]);
        $row = $existing->fetch();
        $uid = (int) $row['id'];
        $userIds[] = $uid;
        if ($stmtUser->rowCount() > 0) {
            $insertedUsers++;
        }

        $stmtFarmer = $pdo->prepare("INSERT IGNORE INTO farmers
            (user_id, village, district_id, state, land_area_acres, primary_crops,
             aadhaar_last4, verification_status, verified_at)
            VALUES (?, ?, ?, 'Maharashtra', ?, ?, ?, 'APPROVED', NOW())");
        $stmtFarmer->execute([
            $uid,
            $villages[$i - 1],
            $districtId,
            number_format(mt_rand(10, 50) + mt_rand(0, 999) / 1000, 3, '.', ''),
            'Wheat, Paddy',
            str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }
    echo "Users: {$insertedUsers} created\n";

    // ── Demo Staff (SA / Manager / Operator) for smoke + security scripts ──
    $demoStaffPassword = 'Admin@1234';
    $demoStaffHash = password_hash($demoStaffPassword, PASSWORD_DEFAULT);
    $insertedStaff = 0;

    $staffDefinitions = [
        // [mobile, username, name, role_id, is_super_admin]
        ['7010000001', 'demo_super_admin', 'Demo Super Admin', 1, 1],
        ['9020000001', 'demo_manager',     'Demo Centre Manager', 3, 0],
        ['9010000001', 'demo_operator',    'Demo Centre Operator', 4, 0],
    ];

    $staffIds = [];
    foreach ($staffDefinitions as [$sMobile, $sUsername, $sName, $sRoleId, $sIsSa]) {
        $stmtStaff = $pdo->prepare("INSERT IGNORE INTO users
            (name, mobile, username, password_hash, role_id, status, verification_status,
             mobile_verified_at, password_set_at, is_super_admin, created_by)
            VALUES (?, ?, ?, ?, ?, 'ACTIVE', 'APPROVED', NOW(), NOW(), ?, 1)");
        $stmtStaff->execute([$sName, $sMobile, $sUsername, $demoStaffHash, $sRoleId, $sIsSa]);

        $existing = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $existing->execute([$sMobile]);
        $row = $existing->fetch();
        $staffId = (int) $row['id'];
        $staffIds[$sUsername] = $staffId;
        if ($stmtStaff->rowCount() > 0) {
            $insertedStaff++;
        }

        if ($sRoleId === 3 || $sRoleId === 4) {
            $stmtStaffCentre = $pdo->prepare("INSERT IGNORE INTO centre_staff
                (centre_id, user_id, role, is_primary, created_at)
                SELECT id, ?, ?, ?, NOW() FROM procurement_centres WHERE code = 'CEN01'
                ON DUPLICATE KEY UPDATE is_primary = ?, deleted_at = NULL");
            $roleLabel = $sRoleId === 3 ? 'CENTRE_MANAGER' : 'CENTRE_OPERATOR';
            $isPrimary = $sRoleId === 3 ? 1 : 0;
            $stmtStaffCentre->execute([$staffId, $roleLabel, $isPrimary, $isPrimary]);
        }
    }
    echo "Staff: {$insertedStaff} created (SA/CM/CO with password " . $demoStaffPassword . ")\n";

    // ── ~30 Bookings covering every state ──
    $bookingCropNames = ['Wheat', 'Paddy', 'Rice', 'Maize', 'Onion', 'Potato'];
    $bookingNumCounter = 0;
    $insertedBookings = 0;

    $demoBookings = [
        // ── in-queue (5 bookings, queue WAITING, token ACTIVE) ──
        ['uid' => 0, 'centre' => 0, 'dateOffset' => 0, 'slotOffset' => 0, 'status' => 'CONFIRMED',
         'crops' => [['Wheat', 500.000]], 'bookState' => 'in-queue'],
        ['uid' => 1, 'centre' => 0, 'dateOffset' => 0, 'slotOffset' => 1, 'status' => 'CONFIRMED',
         'crops' => [['Paddy', 300.000]], 'bookState' => 'in-queue'],
        ['uid' => 2, 'centre' => 2, 'dateOffset' => 1, 'slotOffset' => 0, 'status' => 'CONFIRMED',
         'crops' => [['Maize', 200.000]], 'bookState' => 'in-queue'],
        ['uid' => 3, 'centre' => 0, 'dateOffset' => 1, 'slotOffset' => 1, 'status' => 'CONFIRMED',
         'crops' => [['Wheat', 400.000]], 'bookState' => 'in-queue'],
        ['uid' => 4, 'centre' => 3, 'dateOffset' => 2, 'slotOffset' => 0, 'status' => 'CONFIRMED',
         'crops' => [['Paddy', 250.000]], 'bookState' => 'in-queue'],

        // ── procurement-in-progress (4 bookings, queue COMPLETED, procurement IN_PROGRESS) ──
        ['uid' => 5,  'centre' => 0, 'dateOffset' => 0, 'slotOffset' => 2, 'status' => 'COMPLETED',
         'crops' => [['Wheat', 600.000]], 'bookState' => 'procurement-in-progress'],
        ['uid' => 6,  'centre' => 1, 'dateOffset' => 0, 'slotOffset' => 0, 'status' => 'COMPLETED',
         'crops' => [['Paddy', 350.000]], 'bookState' => 'procurement-in-progress'],
        ['uid' => 7,  'centre' => 2, 'dateOffset' => 1, 'slotOffset' => 1, 'status' => 'COMPLETED',
         'crops' => [['Rice', 275.000]], 'bookState' => 'procurement-in-progress'],
        ['uid' => 8,  'centre' => 3, 'dateOffset' => 2, 'slotOffset' => 1, 'status' => 'COMPLETED',
         'crops' => [['Maize', 180.000]], 'bookState' => 'procurement-in-progress'],

        // ── payment-released (4 bookings, queue COMPLETED, procurement COMPLETED, payment RELEASED) ──
        ['uid' => 9,  'centre' => 0, 'dateOffset' => 0, 'slotOffset' => 3, 'status' => 'COMPLETED',
         'crops' => [['Wheat', 550.000]], 'bookState' => 'payment-released'],
        ['uid' => 10, 'centre' => 1, 'dateOffset' => 1, 'slotOffset' => 0, 'status' => 'COMPLETED',
         'crops' => [['Paddy', 400.000]], 'bookState' => 'payment-released'],
        ['uid' => 11, 'centre' => 2, 'dateOffset' => 1, 'slotOffset' => 2, 'status' => 'COMPLETED',
         'crops' => [['Wheat', 320.000]], 'bookState' => 'payment-released'],
        ['uid' => 12, 'centre' => 3, 'dateOffset' => 2, 'slotOffset' => 2, 'status' => 'COMPLETED',
         'crops' => [['Onion', 150.000]], 'bookState' => 'payment-released'],

        // ── cancelled ──
        ['uid' => 13, 'centre' => 0, 'dateOffset' => 3, 'slotOffset' => 0, 'status' => 'CANCELLED',
         'crops' => [['Potato', 100.000]], 'bookState' => 'cancelled'],

        // ── pending (will expire soon) ──
        ['uid' => 14, 'centre' => 1, 'dateOffset' => 1, 'slotOffset' => 1, 'status' => 'PENDING',
         'crops' => [['Rice', 200.000]], 'bookState' => 'pending'],
    ];

    $queuePositions = array_fill_keys(array_keys($centreIds), 0);

    foreach ($demoBookings as $b) {
        $slotDateOffset = $b['dateOffset'];
        $slotOffset = $b['slotOffset'];
        $cIdx = $b['centre'];

        if (!isset($slotIds[$slotDateOffset][$cIdx][$slotOffset])) {
            continue;
        }
        $slotId = $slotIds[$slotDateOffset][$cIdx][$slotOffset];
        $uid = $userIds[$b['uid']];
        $cid = $centreIds[$b['centre']];

        $bookingNumCounter++;
        $bookingNumber = $bookingPrefix . str_pad((string) $bookingNumCounter, 4, '0', STR_PAD_LEFT);

        $bookingId = insertDemoBooking($pdo, $uid, $cid, $slotId, $bookingNumber, $b['status']);
        if ($bookingId === 0) {
            continue;
        }
        $insertedBookings++;

        $totalQty = 0.0;
        foreach ($b['crops'] as [$cropName, $qty]) {
            insertBookingCrop($pdo, $bookingId, $cropName, $qty);
            $totalQty += $qty;
        }

        switch ($b['bookState']) {
            case 'in-queue':
                $pos = ++$queuePositions[$b['centre']];
                insertQueueEntry($pdo, $bookingId, $cid, 'WAITING', $pos);
                insertDemoToken($pdo, $bookingId, $bookingNumber);
                break;

            case 'procurement-in-progress':
                $crop = $b['crops'][0];
                $cropName = $crop[0];
                $qty = $crop[1];
                $rate = 22.50;
                $procId = insertDemoProcurement($pdo, $bookingId, null, $cid, $uid, $cropName, $qty, 'IN_PROGRESS', $rate, $qty * $rate);
                insertQueueEntry($pdo, $bookingId, $cid, 'COMPLETED', 1);
                insertDemoToken($pdo, $bookingId, $bookingNumber);
                break;

            case 'payment-released':
                $crop = $b['crops'][0];
                $cropName = $crop[0];
                $qty = $crop[1];
                $rate = 24.00;
                $amount = $qty * $rate;
                $procId = insertDemoProcurement($pdo, $bookingId, null, $cid, $uid, $cropName, $qty, 'COMPLETED', $rate, $amount);
                insertDemoPayment($pdo, $procId, $cid, $uid, $amount);
                insertQueueEntry($pdo, $bookingId, $cid, 'COMPLETED', 1);
                insertDemoToken($pdo, $bookingId, $bookingNumber);
                break;

            case 'cancelled':
                $pdo->prepare("UPDATE bookings SET cancelled_at = NOW(), cancelled_by = 'SYSTEM', cancellation_reason = 'Demo cancelled booking' WHERE id = ?")->execute([$bookingId]);
                break;

            case 'pending':
                $pdo->prepare("UPDATE bookings SET updated_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?")->execute([$bookingId]);
                break;
        }
    }
    echo "Bookings: {$insertedBookings} created\n";

    // ── Summary ──
    $totalDemo = $insertedUsers + $insertedCentres + $insertedSlots + $insertedBookings;
    echo "\nDemo seed: {$insertedUsers} users, {$insertedCentres} centres, {$insertedSlots} slots, {$insertedBookings} bookings created\n";
    echo "Total demo rows: {$totalDemo}\n";

    // ── Print demo logins ──
    echo "\n┌───────────────────────────────────────────────────────────────────────────────────────────────┐\n";
    echo "│ Demo Logins — Farmer Accounts                                                                  │\n";
    echo "├───────────────┬─────────────┬────────────────────────────────────────────┬──────┬─────────────┤\n";
    echo "│ Mobile        │ Password    │ Role                                       │ 2FA  │ Username    │\n";
    echo "├───────────────┼─────────────┼────────────────────────────────────────────┼──────┼─────────────┤\n";

    for ($i = 1; $i <= 15; $i++) {
        $mobile = '90000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $twoFa = in_array($i, [1, 2, 3]) ? ' Yes' : '  No';
        $username = 'demo_farmer_' . $i;
        echo "│ {$mobile} │ Demo@1234  │ Farmer                                     │ {$twoFa} │ {$username} │\n";
    }

    echo "└───────────────┴─────────────┴────────────────────────────────────────────┴──────┴─────────────┘\n";

    echo "\nSummary:\n";
    echo "  Users:            {$insertedUsers} (15 farmers, 3 with 2FA)\n";
    echo "  Centres:          {$insertedCentres} (CEN01–CEN05)\n";
    echo "  Slots:            {$insertedSlots}\n";
    echo "  Bookings:         {$insertedBookings}\n";
    echo "    - in-queue:     5  (CONFIRMED + WAITING queue + ACTIVE token)\n";
    echo "    - procurement:  4  (COMPLETED + IN_PROGRESS procurement)\n";
    echo "    - payment:      4  (COMPLETED + RELEASED payment)\n";
    echo "    - cancelled:    1\n";
    echo "    - pending:      1  (will expire soon)\n";
    echo "  Password:         Demo@1234 (all demo users)\n";
    echo "  Total demo logins: 15\n";
}

// ──────────────────────────────────────────────────────────────
// Helper: insert demo booking (idempotent)
// ──────────────────────────────────────────────────────────────
function insertDemoBooking(PDO $pdo, int $userId, int $centreId, int $slotId, string $bookingNumber, string $status): int
{
    $existing = $pdo->prepare("SELECT id FROM bookings WHERE slot_id = ? AND user_id = ? AND status != 'CANCELLED'");
    $existing->execute([$slotId, $userId]);
    $row = $existing->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $slot = $pdo->prepare("SELECT date FROM slots WHERE id = ?");
    $slot->execute([$slotId]);
    $slotRow = $slot->fetch();
    $date = $slotRow ? $slotRow['date'] : date('Y-m-d');

    $stmt = $pdo->prepare("INSERT IGNORE INTO bookings
        (booking_number, user_id, centre_id, slot_id, date, status, crop_count, total_quantity_kg)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0)");
    $stmt->execute([$bookingNumber, $userId, $centreId, $slotId, $date, $status]);

    if ($stmt->rowCount() === 0) {
        $existing->execute([$slotId, $userId]);
        $row = $existing->fetch();
        return $row ? (int) $row['id'] : 0;
    }

    return (int) $pdo->lastInsertId();
}

// ──────────────────────────────────────────────────────────────
// Helper: insert booking crop
// ──────────────────────────────────────────────────────────────
function insertBookingCrop(PDO $pdo, int $bookingId, string $cropName, float $quantity): void
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO booking_crops
        (booking_id, crop_name, quantity_kg, expected_quality, created_at)
        VALUES (?, ?, ?, 'A', NOW())");
    $stmt->execute([$bookingId, $cropName, $quantity]);
}

// ──────────────────────────────────────────────────────────────
// Helper: insert queue entry with auto-incrementing position
// ──────────────────────────────────────────────────────────────
function insertQueueEntry(PDO $pdo, int $bookingId, int $centreId, string $status, int $position): int
{
    $existing = $pdo->prepare("SELECT id FROM queue_entries WHERE booking_id = ?");
    $existing->execute([$bookingId]);
    $row = $existing->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $maxPos = $pdo->prepare("SELECT COALESCE(MAX(position), 0) AS max_pos FROM queue_entries WHERE centre_id = ? AND date = CURDATE()");
    $maxPos->execute([$centreId]);
    $maxRow = $maxPos->fetch();
    $pos = (int) ($maxRow['max_pos'] ?? 0) + 1;

    $slotDate = $pdo->prepare("SELECT date FROM bookings WHERE id = ?");
    $slotDate->execute([$bookingId]);
    $sdRow = $slotDate->fetch();
    $date = $sdRow ? $sdRow['date'] : date('Y-m-d');

    $stmt = $pdo->prepare("INSERT IGNORE INTO queue_entries
        (booking_id, centre_id, date, status, position)
        VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$bookingId, $centreId, $date, $status, $pos]);

    return (int) $pdo->lastInsertId();
}

// ──────────────────────────────────────────────────────────────
// Helper: insert token with deterministic token number
// ──────────────────────────────────────────────────────────────
function insertDemoToken(PDO $pdo, int $bookingId, string $bookingNumber): int
{
    $existing = $pdo->prepare("SELECT id FROM tokens WHERE booking_id = ?");
    $existing->execute([$bookingId]);
    $row = $existing->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $tokenNumber = 'TKN-' . $bookingNumber;
    $tokenHash = hash('sha256', $tokenNumber);
    $qrData = json_encode(['token' => $tokenNumber, 'booking' => $bookingNumber], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("INSERT IGNORE INTO tokens
        (booking_id, token_number, token_hash, qr_data, status)
        VALUES (?, ?, ?, ?, 'ACTIVE')");
    $stmt->execute([$bookingId, $tokenNumber, $tokenHash, $qrData]);

    return (int) $pdo->lastInsertId();
}

// ──────────────────────────────────────────────────────────────
// Helper: insert procurement
// ──────────────────────────────────────────────────────────────
function insertDemoProcurement(
    PDO $pdo,
    int $bookingId,
    ?int $bookingCropId,
    int $centreId,
    int $userId,
    string $cropName,
    float $quantityKg,
    string $status,
    float $ratePerKg,
    float $amount
): int {
    $existing = $pdo->prepare("SELECT id FROM procurements WHERE booking_id = ?");
    $existing->execute([$bookingId]);
    $row = $existing->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $procNum = 'PRC-' . date('Ymd') . '-' . str_pad((string) $bookingId, 4, '0', STR_PAD_LEFT);

    $fields = ['booking_id', 'procurement_number', 'centre_id', 'user_id', 'crop_name', 'quantity_kg',
               'accepted_weight', 'quality_grade', 'rate_per_kg', 'amount', 'approved_amount', 'status'];
    $values = [$bookingId, $procNum, $centreId, $userId, $cropName, $quantityKg,
               $quantityKg, 'A', $ratePerKg, $amount, $amount, $status];

    if ($bookingCropId !== null) {
        $fields[] = 'booking_crop_id';
        $values[] = $bookingCropId;
    }

    if (in_array($status, ['IN_PROGRESS', 'COMPLETED'])) {
        $fields[] = 'started_at';
        $values[] = date('Y-m-d H:i:s');
    }
    if ($status === 'COMPLETED') {
        $fields[] = 'completed_at';
        $values[] = date('Y-m-d H:i:s');
    }

    $columns = implode(', ', $fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $stmt = $pdo->prepare("INSERT IGNORE INTO procurements ({$columns}) VALUES ({$placeholders})");
    $stmt->execute($values);

    if ($stmt->rowCount() === 0) {
        $existing->execute([$bookingId]);
        $row = $existing->fetch();
        return $row ? (int) $row['id'] : 0;
    }

    return (int) $pdo->lastInsertId();
}

// ──────────────────────────────────────────────────────────────
// Helper: insert payment
// ──────────────────────────────────────────────────────────────
function insertDemoPayment(PDO $pdo, int $procurementId, int $centreId, int $userId, float $amount): int
{
    $existing = $pdo->prepare("SELECT id FROM payments WHERE procurement_id = ?");
    $existing->execute([$procurementId]);
    $row = $existing->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO payments
        (procurement_id, centre_id, user_id, amount, status, payment_method, reference, released_at)
        VALUES (?, ?, ?, ?, 'RELEASED', 'BANK_TRANSFER', ?, NOW())");
    $ref = 'DEMO-' . date('YmdHis') . '-' . str_pad((string) $procurementId, 4, '0', STR_PAD_LEFT);
    $stmt->execute([$procurementId, $centreId, $userId, $amount, $ref]);

    return (int) $pdo->lastInsertId();
}
