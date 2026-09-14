<?php

declare(strict_types=1);

function seedTranslations(PDO $pdo): int
{
    $stringsDir = base_path('resources/strings');
    if (!is_dir($stringsDir)) {
        return 0;
    }

    $language = $pdo->prepare("SELECT id FROM languages WHERE code = ? AND is_enabled = 1 LIMIT 1");
    $upsert = $pdo->prepare(
        "INSERT INTO translations (language_id, translation_key, translated_value, updated_by)
         VALUES (?, ?, ?, NULL)
         ON DUPLICATE KEY UPDATE translated_value = VALUES(translated_value), updated_at = CURRENT_TIMESTAMP"
    );

    $count = 0;
    foreach (glob($stringsDir . '/*.php') as $file) {
        $locale = basename($file, '.php');
        $language->execute([$locale]);
        $row = $language->fetch();
        if ($row === false) {
            continue;
        }
        $languageId = (int) $row['id'];

        $pack = require $file;

        foreach ($pack as $key => $value) {
            $key = (string) $key;
            $value = (string) $value;
            if ($key === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,189}$/', $key)) {
                continue;
            }
            $upsert->execute([$languageId, $key, $value]);
            $count++;
        }
    }

    return $count;
}