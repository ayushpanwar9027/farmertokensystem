<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE error_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id VARCHAR(64) NULL DEFAULT NULL,
            endpoint VARCHAR(500) NULL DEFAULT NULL,
            http_method VARCHAR(10) NULL DEFAULT NULL,
            status_code INT NULL DEFAULT NULL,
            error_type VARCHAR(100) NULL DEFAULT NULL,
            error_code VARCHAR(50) NULL DEFAULT NULL,
            severity ENUM('INFO','WARNING','ERROR','CRITICAL') NOT NULL DEFAULT 'ERROR',
            message TEXT NULL DEFAULT NULL,
            file_path VARCHAR(500) NULL DEFAULT NULL,
            line_number INT NULL DEFAULT NULL,
            stack_trace TEXT NULL DEFAULT NULL,
            user_id INT UNSIGNED NULL DEFAULT NULL,
            ip_address VARCHAR(45) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_err_time (created_at),
            KEY idx_err_endpoint (endpoint),
            KEY idx_err_type (error_type),
            KEY idx_err_severity (severity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS error_logs");
    },
];
