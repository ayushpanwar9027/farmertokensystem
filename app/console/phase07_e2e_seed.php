<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

function upsertE2eUser(string $name, string $mobile, string $username, int $roleId, string $password, int $isSa): int
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
        'created_by' => 1,
    ]);
}

$saId = upsertE2eUser('P07 Test SA', '8123456781', 'sa_phase07', 1, 'Sa07Test@1234', 1);
$faId = upsertE2eUser('P07 Farmer A', '8123456782', 'fa_phase07', 5, 'Fa07Test@1234', 0);
$fbId = upsertE2eUser('P07 Farmer B', '8123456783', 'fb_phase07', 5, 'Fb07Test@1234', 0);
$cmId = upsertE2eUser('P07 Centre Mgr', '8123456784', 'cm_phase07', 3, 'Cm07Test@1234', 0);

foreach ([[$faId], [$fbId]] as [$userId]) {
    $farmer = Database::selectOne("SELECT id FROM farmers WHERE user_id = ?", [$userId]);
    $farmerData = [
        'village' => 'E2E Village',
        'district_id' => 1,
        'state' => 'Maharashtra',
        'verification_status' => 'APPROVED',
    ];
    if ($farmer !== null) {
        Database::update('farmers', $farmerData, 'user_id = ?', [$userId]);
    } else {
        Database::insert('farmers', array_merge($farmerData, ['user_id' => $userId]));
    }
}

$centre = Database::selectOne("SELECT id FROM procurement_centres WHERE code = 'P07C1'");
if ($centre !== null) {
    Database::update('procurement_centres', ['status' => 'ACTIVE', 'deleted_at' => null], 'id = ?', [(int) $centre['id']]);
    $centreId = (int) $centre['id'];
} else {
    $centreId = (int) Database::insert('procurement_centres', [
        'name' => 'P07 E2E Centre',
        'code' => 'P07C1',
        'district_id' => 1,
        'address' => 'Pune',
        'contact_phone' => '8123456784',
        'working_hours_start' => '09:00:00',
        'working_hours_end' => '17:00:00',
        'daily_capacity' => 100,
        'slot_duration_minutes' => 30,
        'manager_user_id' => $cmId,
        'status' => 'ACTIVE',
    ]);
}

$staff = Database::selectOne("SELECT id FROM centre_staff WHERE user_id = ? AND role = 'CENTRE_MANAGER'", [$cmId]);
$staffData = ['centre_id' => $centreId, 'is_primary' => 1, 'deleted_at' => null];
if ($staff !== null) {
    Database::update('centre_staff', $staffData, 'id = ?', [(int) $staff['id']]);
} else {
    Database::insert('centre_staff', array_merge($staffData, ['user_id' => $cmId, 'role' => 'CENTRE_MANAGER']));
}

file_put_contents(base_path('storage/phase07_e2e_context.json'), json_encode([
    'sa' => ['mobile' => '8123456781', 'password' => 'Sa07Test@1234', 'id' => $saId],
    'fa' => ['mobile' => '8123456782', 'password' => 'Fa07Test@1234', 'id' => $faId],
    'fb' => ['mobile' => '8123456783', 'password' => 'Fb07Test@1234', 'id' => $fbId],
    'cm' => ['mobile' => '8123456784', 'password' => 'Cm07Test@1234', 'id' => $cmId],
    'centre' => ['id' => $centreId],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "SA={$saId} FA={$faId} FB={$fbId} CM={$cmId} CENTRE={$centreId}\n";
echo "ctx written\n";