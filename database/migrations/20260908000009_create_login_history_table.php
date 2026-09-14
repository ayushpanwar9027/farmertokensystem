<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE login_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL DEFAULT NULL,
            mobile VARCHAR(15) NULL DEFAULT NULL,
            login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            logout_at DATETIME NULL DEFAULT NULL,
            ip_address VARCHAR(45) NULL DEFAULT NULL,
            user_agent VARCHAR(500) NULL DEFAULT NULL,
            platform VARCHAR(30) NULL DEFAULT NULL,
            device_name VARCHAR(190) NULL DEFAULT NULL,
            status ENUM('SUCCESS','FAILURE') NOT NULL,
            failure_reason VARCHAR(190) NULL DEFAULT NULL,
            session_id INT UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_login_user (user_id),
            KEY idx_login_at (login_at),
            KEY idx_login_status (status),
            KEY idx_login_mobile (mobile)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS login_history");
    },
];
