<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE procurement_centres MODIFY COLUMN `status` ENUM('ACTIVE','INACTIVE','CLOSED') NOT NULL DEFAULT 'ACTIVE'");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE procurement_centres MODIFY COLUMN `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'");
    },
];
