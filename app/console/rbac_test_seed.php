<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

function q(string $sql, array $params = []): void
{
    Database::query($sql, $params);
}

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

q("SET FOREIGN_KEY_CHECKS = 0");

$users = [
    ['DA_TEST_DA', '7123456701', 'da_test1', 2, 'DaTest@1234'],
    ['DA_TEST_CO', '7123456702', 'co_test1', 4, 'CoTest@1234'],
    ['DA_TEST_CM', '7123456703', 'cm_test1', 3, 'CmTest@1234'],
    ['DA_TEST_SA', '7123456704', 'sa_temp1', 1, 'SaTest@1234'],
    ['DA_TEST_SA2', '7123456705', 'sa_temp2', 1, 'SaTest@1234'],
];

$userIds = [];
foreach ($users as [$name, $mobile, $username, $roleId, $password]) {
    $existing = Database::selectOne("SELECT id FROM users WHERE mobile = ?", [$mobile]);
    if ($existing !== null) {
        $userId = (int) $existing['id'];
        q("UPDATE users SET name = ?, username = ?, role_id = ?, status = 'ACTIVE', verification_status = 'APPROVED', is_super_admin = ? WHERE id = ?", [$name, $username, $roleId, $roleId === 1 ? 1 : 0, $userId]);
    } else {
        $userId = (int) Database::insert('users', [
            'name' => $name,
            'mobile' => $mobile,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $roleId,
            'status' => 'ACTIVE',
            'verification_status' => 'APPROVED',
            'mobile_verified_at' => date('Y-m-d H:i:s'),
            'password_set_at' => date('Y-m-d H:i:s'),
            'is_super_admin' => $roleId === 1 ? 1 : 0,
            'created_by' => 1,
        ]);
    }
    $userIds[$name] = $userId;
    out("user {$name}: id={$userId}");
}

q("DELETE FROM centre_staff WHERE centre_id IN (SELECT id FROM procurement_centres WHERE code LIKE 'TEST-%')");
q("DELETE FROM procurement_centres WHERE code LIKE 'TEST-%'");
q("DELETE FROM users WHERE mobile IN ('7123456798', '7123456799')");

$centreA = (int) Database::insert('procurement_centres', [
    'name' => 'Test Centre Pune',
    'code' => 'TEST-A1',
    'district_id' => 1,
    'address' => 'Test Address A',
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'working_days' => 'MON,TUE,WED,THU,FRI',
    'status' => 'ACTIVE',
]);

$centreB = (int) Database::insert('procurement_centres', [
    'name' => 'Test Centre Nashik',
    'code' => 'TEST-B2',
    'district_id' => 2,
    'address' => 'Test Address B',
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'working_days' => 'MON,TUE,WED,THU,FRI',
    'status' => 'ACTIVE',
]);

$coId = $userIds['DA_TEST_CO'];
$cmId = $userIds['DA_TEST_CM'];
$daId = $userIds['DA_TEST_DA'];
$saId = $userIds['DA_TEST_SA'];
$sa2Id = $userIds['DA_TEST_SA2'];

q("INSERT INTO centre_staff (centre_id, user_id, role, is_primary) VALUES (?, ?, 'CENTRE_OPERATOR', 0) ON DUPLICATE KEY UPDATE role = 'CENTRE_OPERATOR'", [$centreA, $coId]);
q("INSERT INTO centre_staff (centre_id, user_id, role, is_primary) VALUES (?, ?, 'CENTRE_MANAGER', 1) ON DUPLICATE KEY UPDATE role = 'CENTRE_MANAGER'", [$centreA, $cmId]);
q("INSERT INTO centre_staff (centre_id, user_id, role, is_primary) VALUES (?, ?, 'CENTRE_MANAGER', 0) ON DUPLICATE KEY UPDATE role = 'CENTRE_MANAGER'", [$centreA, $daId]);

q("SET FOREIGN_KEY_CHECKS = 1");

out("centreA={$centreA} centreB={$centreB} daId={$daId} coId={$coId} cmId={$cmId} saId={$saId} sa2Id={$sa2Id}");

file_put_contents(base_path('storage/rbac_test_context.json'), json_encode([
    'centreA' => $centreA,
    'centreB' => $centreB,
    'daId' => $daId,
    'coId' => $coId,
    'cmId' => $cmId,
    'saId' => $saId,
    'sa2Id' => $sa2Id,
    'userIds' => $userIds,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

out('ctx written');