<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE notifications
            MODIFY COLUMN channel ENUM('PUSH','IN_APP','BOTH') NOT NULL DEFAULT 'IN_APP'");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE notifications
            MODIFY COLUMN channel ENUM('SMS','IN_APP','BOTH') NOT NULL DEFAULT 'IN_APP'");
    },
];