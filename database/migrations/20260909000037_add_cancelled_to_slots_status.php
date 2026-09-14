<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE slots
            MODIFY COLUMN `status` ENUM('ACTIVE','INACTIVE','FULL','CANCELLED') NOT NULL DEFAULT 'ACTIVE'");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE slots
            MODIFY COLUMN `status` ENUM('ACTIVE','INACTIVE','FULL') NOT NULL DEFAULT 'ACTIVE'");
    },
];
