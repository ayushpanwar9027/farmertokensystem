<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\RbacService;
use App\Services\ScopeService;

$ctxPath = base_path('storage/rbac_test_context.json');
$ctx = json_decode(file_get_contents($ctxPath), true);

$pass = 0;
$fail = 0;

function check(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) {
        $pass++;
        echo "  PASS: {$label}" . PHP_EOL;
    } else {
        $fail++;
        echo "  FAIL: {$label}" . PHP_EOL;
    }
}

function userId(int $id): array
{
    $row = Database::selectOne("SELECT * FROM users WHERE id = ?", [$id]);
    if ($row === null) {
        throw new RuntimeException('user not found ' . $id);
    }
    $row['role'] = (new \App\Models\User())->roleName((int) $row['role_id']);
    return $row;
}

$sa = userId($ctx['saId']);
$sa2 = userId($ctx['sa2Id']);
$da = userId($ctx['daId']);
$co = userId($ctx['coId']);
$cm = userId($ctx['cmId']);
$farmer = userId(1);

$rbac = new RbacService();
$scope = new ScopeService();

echo "== Super Admin bypass ==\n";
check('SA can any permission', $rbac->can($sa, 'manage_system_settings'));
check('SA can unknown/dangling key', $rbac->can($sa, 'does_not_exist'));
check('SA effective perms includes manage_staff', in_array('manage_staff', $rbac->effectivePermissions((int) $sa['id'], $sa), true));

echo "== FARMER deny-by-default ==\n";
check('FARMER cannot manage_staff', !$rbac->can($farmer, 'manage_staff'));
check('FARMER can book_slot', $rbac->can($farmer, 'book_slot'));

echo "== CO role defaults ==\n";
check('CO can manage_queue (role default)', $rbac->can($co, 'manage_queue'));
check('CO cannot manage_slots (not granted to role)', !$rbac->can($co, 'manage_slots'));
check('CO cannot manage_staff', !$rbac->can($co, 'manage_staff'));

echo "== negative priority: revoke beats role grant ==\n";
(new \App\Models\UserPermission())->upsert((int) $co['id'], (int) (new \App\Models\Permission())->idByName('manage_queue'), false, (int) $sa['id']);
$rbac->clearCache((int) $co['id']);
check('CO manage_queue revoked by override -> denied', !$rbac->can(userId($co['id']), 'manage_queue'));
check('negative priority = true config honored', true);

echo "== grant override: direct grant adds permission ==\n";
(new \App\Models\UserPermission())->upsert((int) $co['id'], (int) (new \App\Models\Permission())->idByName('manage_slots'), true, (int) $sa['id']);
$rbac->clearCache((int) $co['id']);
check('CO manage_slots granted by override -> allowed', $rbac->can(userId($co['id']), 'manage_slots'));

echo "== remove override restores role default ==\n";
(new \App\Models\UserPermission())->remove((int) $co['id'], (int) (new \App\Models\Permission())->idByName('manage_queue'));
(new \App\Models\UserPermission())->remove((int) $co['id'], (int) (new \App\Models\Permission())->idByName('manage_slots'));
$rbac->clearCache((int) $co['id']);
check('CO manage_queue back after override removal', $rbac->can(userId($co['id']), 'manage_queue'));
check('CO manage_slots denied again after override removal', !$rbac->can(userId($co['id']), 'manage_slots'));

echo "== role change invalidates cached perms ==\n";
$cmPermsBefore = $rbac->effectivePermissions((int) $cm['id'], $cm);
check('CM has manage_staff before', in_array('manage_staff', $cmPermsBefore, true));
Database::update('users', ['role_id' => 4], 'id = ?', [(int) $cm['id']]);
$rbac->clearCache((int) $cm['id']);
check('CM->operator no longer has manage_staff after role change', !$rbac->can(userId($cm['id']), 'manage_staff'));
Database::update('users', ['role_id' => 3], 'id = ?', [(int) $cm['id']]);
$rbac->clearCache((int) $cm['id']);
check('CM restored has manage_staff again', $rbac->can(userId($cm['id']), 'manage_staff'));

echo "== deny-by-default unknown user ==\n";
check('null user denied', !$rbac->can(null, 'manage_staff'));

echo "== ScopeService ==\n";
$centreA = (int) $ctx['centreA'];
$centreB = (int) $ctx['centreB'];

check('SA can access any centre', $scope->canAccessCentre($sa, $centreB));
check('DA can access centre in district 1', $scope->canAccessCentre($da, $centreA));
check('DA cannot access centre in district 2', !$scope->canAccessCentre($da, $centreB));
check('CO can access own centre A', $scope->canAccessCentre($co, $centreA));
check('CO cannot access other centre B', !$scope->canAccessCentre($co, $centreB));
check('CM can access own centre A', $scope->canAccessCentre($cm, $centreA));
check('FARMER cannot access any centre', !$scope->canAccessCentre($farmer, $centreA));
$daDistricts = $scope->districtIdsFor($da);
check('DA district scope includes district 1', in_array(1, $daDistricts, true) && !in_array(2, $daDistricts, true));
$coCentres = $scope->centreIdsFor($co);
check('CO centre scope = [centreA]', $coCentres === [$centreA]);

echo "== summary ==\n";
echo "PASS={$pass} FAIL={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);