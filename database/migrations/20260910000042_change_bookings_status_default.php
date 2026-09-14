<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE bookings
            MODIFY COLUMN `status` ENUM('PENDING','CONFIRMED','CANCELLED','COMPLETED','EXPIRED') NOT NULL DEFAULT 'PENDING'");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE bookings
            MODIFY COLUMN `status` ENUM('PENDING','CONFIRMED','CANCELLED','COMPLETED','EXPIRED') NOT NULL DEFAULT 'CONFIRMED'");
    },
];
