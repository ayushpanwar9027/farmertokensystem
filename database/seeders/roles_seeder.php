<?php

declare(strict_types=1);

function seedRoles(PDO $pdo): int
{
    $roles = [
        ['name' => 'SUPER_ADMIN',      'display_name' => 'Super Admin',      'is_system' => 1, 'level' => 100, 'description' => 'Full system control across all districts and centres'],
        ['name' => 'DISTRICT_ADMIN',   'display_name' => 'District Admin',   'is_system' => 1, 'level' => 70,  'description' => 'District-level management within assigned district'],
        ['name' => 'CENTRE_MANAGER',   'display_name' => 'Centre Manager',   'is_system' => 1, 'level' => 50,  'description' => 'Centre management within assigned procurement centre'],
        ['name' => 'CENTRE_OPERATOR',  'display_name' => 'Centre Operator',  'is_system' => 1, 'level' => 30,  'description' => 'Day-to-day operations within assigned procurement centre'],
        ['name' => 'FARMER',           'display_name' => 'Farmer',           'is_system' => 1, 'level' => 10,  'description' => 'Self-service farmer account via Flutter app'],
    ];

    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name, display_name, description, is_system, level) VALUES (?, ?, ?, ?, ?)");

    foreach ($roles as $role) {
        $stmt->execute([$role['name'], $role['display_name'], $role['description'], $role['is_system'], $role['level']]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
