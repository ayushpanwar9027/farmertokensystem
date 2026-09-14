<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE support_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            subject VARCHAR(190) NOT NULL,
            category VARCHAR(50) NULL DEFAULT NULL,
            message TEXT NOT NULL,
            related_entity_type VARCHAR(50) NULL DEFAULT NULL,
            related_entity_id INT UNSIGNED NULL DEFAULT NULL,
            status ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN',
            assigned_to INT UNSIGNED NULL DEFAULT NULL,
            resolved_at DATETIME NULL DEFAULT NULL,
            resolved_by INT UNSIGNED NULL DEFAULT NULL,
            resolution_notes TEXT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_support_user (user_id),
            KEY idx_support_status (status),
            CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS support_requests");
    },
];
