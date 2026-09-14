<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE translations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            language_id INT UNSIGNED NOT NULL,
            translation_key VARCHAR(190) NOT NULL,
            translated_value TEXT NOT NULL,
            updated_by INT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_translation (language_id, translation_key),
            CONSTRAINT fk_trans_lang FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS translations");
    },
];
