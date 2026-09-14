<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE system_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            key_name VARCHAR(100) NOT NULL,
            key_value TEXT NULL DEFAULT NULL,
            value_type ENUM('STRING','INT','BOOL','JSON','FLOAT') NOT NULL DEFAULT 'STRING',
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
            group_name VARCHAR(50) NULL DEFAULT NULL,
            description VARCHAR(500) NULL DEFAULT NULL,
            updated_by INT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_setting_key (key_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS system_settings");
    },
];
