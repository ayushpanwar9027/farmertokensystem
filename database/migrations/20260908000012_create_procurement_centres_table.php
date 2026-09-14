<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE procurement_centres (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            code VARCHAR(20) NOT NULL,
            district_id INT UNSIGNED NOT NULL,
            address VARCHAR(500) NOT NULL,
            contact_phone VARCHAR(15) NULL DEFAULT NULL,
            contact_email VARCHAR(191) NULL DEFAULT NULL,
            working_hours_start TIME NOT NULL,
            working_hours_end TIME NOT NULL,
            working_days VARCHAR(50) NOT NULL DEFAULT 'MON,TUE,WED,THU,FRI',
            daily_capacity INT UNSIGNED NOT NULL DEFAULT 100,
            slot_duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
            manager_user_id INT UNSIGNED NULL DEFAULT NULL,
            latitude DECIMAL(10,7) NULL DEFAULT NULL,
            longitude DECIMAL(10,7) NULL DEFAULT NULL,
            status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_centre_code (code),
            KEY idx_centre_district (district_id),
            KEY idx_centre_status (status),
            CONSTRAINT fk_centre_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS procurement_centres");
    },
];
