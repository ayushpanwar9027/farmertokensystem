<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE file_references (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            file_id INT UNSIGNED NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            source_id INT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_file_ref (file_id, source_type, source_id),
            KEY idx_file_ref_file (file_id),
            CONSTRAINT fk_fref_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS file_references");
    },
];
