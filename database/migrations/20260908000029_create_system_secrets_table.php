<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE system_secrets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            key_name VARCHAR(100) NOT NULL,
            encrypted_value TEXT NOT NULL,
            iv VARCHAR(64) NOT NULL,
            is_set TINYINT(1) NOT NULL DEFAULT 0,
            last_rotated_at DATETIME NULL DEFAULT NULL,
            last_updated_by INT UNSIGNED NULL DEFAULT NULL,
            description VARCHAR(500) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_secret_key (key_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS system_secrets");
    },
];
