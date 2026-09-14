<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE procurements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id INT UNSIGNED NOT NULL,
            booking_crop_id INT UNSIGNED NULL DEFAULT NULL,
            centre_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            crop_name VARCHAR(100) NOT NULL,
            variety VARCHAR(100) NULL DEFAULT NULL,
            quantity_kg DECIMAL(12,3) NOT NULL,
            quality_grade VARCHAR(10) NULL DEFAULT NULL,
            moisture_percent DECIMAL(5,2) NULL DEFAULT NULL,
            status ENUM('PENDING','VERIFIED','IN_PROGRESS','COMPLETED','REJECTED') NOT NULL DEFAULT 'PENDING',
            rate_per_kg DECIMAL(12,2) NULL DEFAULT NULL,
            amount DECIMAL(12,2) NULL DEFAULT NULL,
            notes VARCHAR(500) NULL DEFAULT NULL,
            rejection_reason VARCHAR(500) NULL DEFAULT NULL,
            verified_at DATETIME NULL DEFAULT NULL,
            verified_by INT UNSIGNED NULL DEFAULT NULL,
            started_at DATETIME NULL DEFAULT NULL,
            started_by INT UNSIGNED NULL DEFAULT NULL,
            completed_at DATETIME NULL DEFAULT NULL,
            completed_by INT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_proc_booking (booking_id),
            KEY idx_proc_centre_date (centre_id, created_at),
            KEY idx_proc_status (status),
            KEY idx_proc_user (user_id),
            CONSTRAINT fk_proc_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_proc_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT,
            CONSTRAINT fk_proc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS procurements");
    },
];
