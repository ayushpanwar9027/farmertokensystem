<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE bookings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_number VARCHAR(30) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            centre_id INT UNSIGNED NOT NULL,
            slot_id INT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            status ENUM('PENDING','CONFIRMED','CANCELLED','COMPLETED','EXPIRED') NOT NULL DEFAULT 'CONFIRMED',
            crop_count INT UNSIGNED NOT NULL DEFAULT 0,
            total_quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
            cancelled_at DATETIME NULL DEFAULT NULL,
            cancelled_by VARCHAR(20) NULL DEFAULT NULL,
            cancelled_by_user_id INT UNSIGNED NULL DEFAULT NULL,
            cancellation_reason VARCHAR(500) NULL DEFAULT NULL,
            completed_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_booking_number (booking_number),
            UNIQUE KEY uq_booking_slot_user (slot_id, user_id, status),
            KEY idx_booking_user (user_id),
            KEY idx_booking_slot (slot_id),
            KEY idx_booking_centre_date (centre_id, date),
            KEY idx_booking_status (status),
            CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_booking_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT,
            CONSTRAINT fk_booking_slot FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS bookings");
    },
];
