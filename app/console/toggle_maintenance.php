<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$mode = $argv[1] ?? 'off';
$value = $mode === 'on' ? '1' : '0';

Database::query(
    "UPDATE system_settings SET key_value = ? WHERE key_name = 'maintenance_mode'",
    [$value]
);

if ($mode === 'on') {
    Database::query(
        "UPDATE system_settings SET key_value = ? WHERE key_name = 'maintenance_message'",
        ['Scheduled maintenance in progress']
    );
}

echo "maintenance_mode set to {$value}\n";