<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL,
            title VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            data JSON NULL DEFAULT NULL,
            channel ENUM('SMS','IN_APP','BOTH') NOT NULL DEFAULT 'IN_APP',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notif_user (user_id),
            KEY idx_notif_user_read (user_id, is_read),
            KEY idx_notif_type (type),
            CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS notifications");
    },
];
