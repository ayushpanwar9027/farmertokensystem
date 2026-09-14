<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE remember_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            device_id VARCHAR(100) NULL DEFAULT NULL,
            device_name VARCHAR(190) NULL DEFAULT NULL,
            platform VARCHAR(30) NULL DEFAULT NULL,
            ip_address VARCHAR(45) NULL DEFAULT NULL,
            user_agent VARCHAR(500) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL DEFAULT NULL,
            revoked_by INT UNSIGNED NULL DEFAULT NULL,
            status ENUM('ACTIVE','EXPIRED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
            PRIMARY KEY (id),
            UNIQUE KEY uq_remember_token (token_hash),
            KEY idx_remember_user (user_id),
            KEY idx_remember_expires (expires_at),
            CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS remember_tokens");
    },
];
