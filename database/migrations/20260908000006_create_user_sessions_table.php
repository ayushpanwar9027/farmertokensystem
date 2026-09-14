<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE user_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            session_token_hash VARCHAR(64) NOT NULL,
            device_id VARCHAR(100) NULL DEFAULT NULL,
            device_name VARCHAR(190) NULL DEFAULT NULL,
            platform VARCHAR(30) NULL DEFAULT NULL,
            app_version VARCHAR(20) NULL DEFAULT NULL,
            ip_address VARCHAR(45) NULL DEFAULT NULL,
            user_agent VARCHAR(500) NULL DEFAULT NULL,
            remember INT UNSIGNED NULL DEFAULT NULL,
            is_remembered TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL DEFAULT NULL,
            revoked_by INT UNSIGNED NULL DEFAULT NULL,
            revoked_reason VARCHAR(190) NULL DEFAULT NULL,
            status ENUM('ACTIVE','EXPIRED','REVOKED','LOGGED_OUT') NOT NULL DEFAULT 'ACTIVE',
            PRIMARY KEY (id),
            UNIQUE KEY uq_session_token (session_token_hash),
            KEY idx_session_user (user_id),
            KEY idx_session_expires (expires_at),
            KEY idx_session_status (status),
            CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS user_sessions");
    },
];
