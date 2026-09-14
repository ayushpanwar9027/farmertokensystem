<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE otp_verifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mobile VARCHAR(15) NOT NULL,
            purpose ENUM('REGISTRATION','LOGIN','PASSWORD_RESET') NOT NULL,
            verification_id VARCHAR(40) NOT NULL,
            otp_hash VARCHAR(64) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
            expires_at DATETIME NOT NULL,
            resend_at DATETIME NULL DEFAULT NULL,
            verified_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_otp_verification_id (verification_id),
            KEY idx_otp_mobile (mobile),
            KEY idx_otp_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS otp_verifications");
    },
];
