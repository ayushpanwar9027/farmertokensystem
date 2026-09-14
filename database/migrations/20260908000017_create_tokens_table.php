<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id INT UNSIGNED NOT NULL,
            token_number VARCHAR(40) NOT NULL,
            qr_data VARCHAR(200) NOT NULL,
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            issued_by VARCHAR(20) NULL DEFAULT NULL,
            status ENUM('ACTIVE','USED','EXPIRED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
            revoked_at DATETIME NULL DEFAULT NULL,
            revoked_reason VARCHAR(190) NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_token_booking (booking_id),
            UNIQUE KEY uq_token_number (token_number),
            CONSTRAINT fk_token_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS tokens");
    },
];
