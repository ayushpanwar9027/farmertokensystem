<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

$action = $argv[1] ?? '';

if ($action === 'suspend-core') {
    Database::update('users', ['status' => 'SUSPENDED'], 'id IN (2, 5)', []);
    echo 'core suspended' . PHP_EOL;
} elseif ($action === 'restore-core') {
    Database::update('users', ['status' => 'ACTIVE'], 'id IN (2, 5)', []);
    echo 'core restored' . PHP_EOL;
} else {
    echo 'Usage: php app/console/rbac_sa_helper.php [suspend-core|restore-core]' . PHP_EOL;
    exit(1);
}