<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE slots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            centre_id INT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            capacity INT UNSIGNED NOT NULL DEFAULT 10,
            booked_count INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('ACTIVE','INACTIVE','FULL') NOT NULL DEFAULT 'ACTIVE',
            created_by INT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_slot_centre (centre_id),
            KEY idx_slot_date (date),
            UNIQUE KEY uq_slot_centre_date_time (centre_id, date, start_time, end_time),
            CONSTRAINT fk_slot_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS slots");
    },
];
