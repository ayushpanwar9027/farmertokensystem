<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE roles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            description VARCHAR(500) NULL DEFAULT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            level INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_roles_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS roles");
    },
];
