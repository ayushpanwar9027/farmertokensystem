<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE payments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            procurement_id INT UNSIGNED NOT NULL,
            centre_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            status ENUM('PENDING','PROCESSING','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
            payment_method VARCHAR(30) NULL DEFAULT NULL,
            reference VARCHAR(100) NULL DEFAULT NULL,
            processed_at DATETIME NULL DEFAULT NULL,
            processed_by INT UNSIGNED NULL DEFAULT NULL,
            failure_reason VARCHAR(500) NULL DEFAULT NULL,
            notes VARCHAR(500) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_payment_procurement (procurement_id),
            KEY idx_payment_centre_date (centre_id, created_at),
            KEY idx_payment_status (status),
            KEY idx_payment_user (user_id),
            CONSTRAINT fk_pay_procurement FOREIGN KEY (procurement_id) REFERENCES procurements(id) ON DELETE CASCADE,
            CONSTRAINT fk_pay_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS payments");
    },
];
