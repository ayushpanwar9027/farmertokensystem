<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE notification_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_id INT UNSIGNED NULL DEFAULT NULL,
            user_id INT UNSIGNED NULL DEFAULT NULL,
            recipient VARCHAR(15) NULL DEFAULT NULL,
            channel ENUM('SMS','EMAIL') NOT NULL DEFAULT 'SMS',
            event_type VARCHAR(30) NOT NULL,
            status ENUM('PENDING','SENT','FAILED','RETRY') NOT NULL DEFAULT 'PENDING',
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
            next_retry_at DATETIME NULL DEFAULT NULL,
            sent_at DATETIME NULL DEFAULT NULL,
            error_message VARCHAR(500) NULL DEFAULT NULL,
            provider_response TEXT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_nlog_status_retry (status, next_retry_at),
            KEY idx_nlog_notification (notification_id),
            KEY idx_nlog_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS notification_logs");
    },
];
