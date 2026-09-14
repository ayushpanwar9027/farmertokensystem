<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL DEFAULT NULL,
            user_name VARCHAR(190) NULL DEFAULT NULL,
            user_role VARCHAR(50) NULL DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            module VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NULL DEFAULT NULL,
            entity_id INT UNSIGNED NULL DEFAULT NULL,
            old_value JSON NULL DEFAULT NULL,
            new_value JSON NULL DEFAULT NULL,
            reason VARCHAR(500) NULL DEFAULT NULL,
            ip_address VARCHAR(45) NULL DEFAULT NULL,
            user_agent VARCHAR(500) NULL DEFAULT NULL,
            request_id VARCHAR(64) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_audit_time (created_at),
            KEY idx_audit_user (user_id),
            KEY idx_audit_action (action),
            KEY idx_audit_module (module),
            KEY idx_audit_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS audit_logs");
    },
];
