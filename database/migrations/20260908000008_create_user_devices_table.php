<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE user_devices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            device_id VARCHAR(100) NOT NULL,
            device_name VARCHAR(190) NULL DEFAULT NULL,
            platform VARCHAR(30) NULL DEFAULT NULL,
            app_version VARCHAR(20) NULL DEFAULT NULL,
            last_ip_address VARCHAR(45) NULL DEFAULT NULL,
            last_seen_at DATETIME NULL DEFAULT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_device (user_id, device_id),
            CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS user_devices");
    },
];
