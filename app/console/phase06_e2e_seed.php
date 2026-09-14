<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

function upsertTestUser(string $name, string $mobile, string $username, int $roleId, string $password, int $isSa): int
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

$saId = upsertTestUser('P06 Test SA', '7123456781', 'sa_phase06', 1, 'Sa06Test@1234', 1);
$daId = upsertTestUser('P06 Test DA', '7123456782', 'da_phase06', 2, 'Da06Test@1234', 0);

file_put_contents(base_path('storage/phase06_e2e_context.json'), json_encode([
    'sa' => ['mobile' => '7123456781', 'password' => 'Sa06Test@1234', 'id' => $saId],
    'da' => ['mobile' => '7123456782', 'password' => 'Da06Test@1234', 'id' => $daId],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "SA id={$saId} DA id={$daId}\n";
echo "ctx written\n";