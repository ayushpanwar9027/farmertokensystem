<?php

declare(strict_types=1);

function seedDistricts(PDO $pdo): int
{
    $districts = [
        ['name' => 'Pune',        'state' => 'Maharashtra', 'code' => 'MH-PU'],
        ['name' => 'Nashik',      'state' => 'Maharashtra', 'code' => 'MH-NK'],
        ['name' => 'Nagpur',      'state' => 'Maharashtra', 'code' => 'MH-NG'],
        ['name' => 'Ahmednagar',  'state' => 'Maharashtra', 'code' => 'MH-AN'],
        ['name' => 'Solapur',     'state' => 'Maharashtra', 'code' => 'MH-SO'],
    ];

    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO districts (name, state, code, is_active) VALUES (?, ?, ?, 1)");

    foreach ($districts as $d) {
        $stmt->execute([$d['name'], $d['state'], $d['code']]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
