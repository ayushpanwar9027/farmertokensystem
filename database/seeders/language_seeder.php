<?php

declare(strict_types=1);

function seedLanguages(PDO $pdo): int
{
    $languages = [
        ['code' => 'en', 'name' => 'English',  'native_name' => 'English', 'is_default' => 1, 'is_enabled' => 1],
        ['code' => 'hi', 'name' => 'Hindi',    'native_name' => 'हिन्दी',    'is_default' => 0, 'is_enabled' => 1],
    ];

    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO languages (code, name, native_name, is_default, is_enabled) VALUES (?, ?, ?, ?, ?)");

    foreach ($languages as $lang) {
        $stmt->execute([$lang['code'], $lang['name'], $lang['native_name'], $lang['is_default'], $lang['is_enabled']]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
