<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE queue_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id INT UNSIGNED NOT NULL,
            centre_id INT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            status ENUM('WAITING','CALLED','IN_PROGRESS','COMPLETED','SKIPPED','CANCELLED') NOT NULL DEFAULT 'WAITING',
            position INT UNSIGNED NOT NULL,
            arrived_at DATETIME NULL DEFAULT NULL,
            called_at DATETIME NULL DEFAULT NULL,
            called_by INT UNSIGNED NULL DEFAULT NULL,
            started_at DATETIME NULL DEFAULT NULL,
            started_by INT UNSIGNED NULL DEFAULT NULL,
            completed_at DATETIME NULL DEFAULT NULL,
            completed_by INT UNSIGNED NULL DEFAULT NULL,
            skipped_at DATETIME NULL DEFAULT NULL,
            skipped_by INT UNSIGNED NULL DEFAULT NULL,
            skip_reason VARCHAR(500) NULL DEFAULT NULL,
            cancelled_at DATETIME NULL DEFAULT NULL,
            notes VARCHAR(500) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_queue_booking (booking_id),
            KEY idx_queue_centre_date (centre_id, date),
            KEY idx_queue_status (status),
            KEY idx_queue_created (created_at),
            CONSTRAINT fk_queue_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_queue_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS queue_entries");
    },
];
