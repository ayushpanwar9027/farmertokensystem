<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            mobile VARCHAR(15) NOT NULL,
            email VARCHAR(191) NULL DEFAULT NULL,
            username VARCHAR(50) NULL DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            status ENUM('ACTIVE','INACTIVE','PENDING','LOCKED') NOT NULL DEFAULT 'ACTIVE',
            verification_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
            verification_rejected_reason VARCHAR(500) NULL DEFAULT NULL,
            email_verified_at DATETIME NULL DEFAULT NULL,
            mobile_verified_at DATETIME NULL DEFAULT NULL,
            password_set_at DATETIME NULL DEFAULT NULL,
            last_login_at DATETIME NULL DEFAULT NULL,
            remember_token VARCHAR(255) NULL DEFAULT NULL,
            profile_image_file_id INT UNSIGNED NULL DEFAULT NULL,
            created_by INT UNSIGNED NULL DEFAULT NULL,
            is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_mobile (mobile),
            UNIQUE KEY uq_users_email (email),
            UNIQUE KEY uq_users_username (username),
            KEY idx_users_role (role_id),
            KEY idx_users_status (status),
            KEY idx_users_verification (verification_status),
            CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS users");
    },
];
