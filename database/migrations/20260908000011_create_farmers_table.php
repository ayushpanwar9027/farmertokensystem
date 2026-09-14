<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE farmers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            alternative_mobile VARCHAR(15) NULL DEFAULT NULL,
            village VARCHAR(190) NOT NULL,
            district_id INT UNSIGNED NOT NULL,
            state VARCHAR(100) NOT NULL,
            pincode VARCHAR(10) NULL DEFAULT NULL,
            land_area_acres DECIMAL(10,3) NULL DEFAULT NULL,
            primary_crops VARCHAR(1000) NULL DEFAULT NULL,
            aadhaar_last4 VARCHAR(4) NULL DEFAULT NULL,
            verification_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
            verification_rejected_reason VARCHAR(500) NULL DEFAULT NULL,
            verified_at DATETIME NULL DEFAULT NULL,
            verified_by INT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_farmer_user (user_id),
            KEY idx_farmer_district (district_id),
            KEY idx_farmer_verification (verification_status),
            CONSTRAINT fk_farmer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_farmer_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS farmers");
    },
];
