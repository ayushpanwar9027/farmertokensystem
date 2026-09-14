<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE notification_logs
            MODIFY COLUMN recipient VARCHAR(190) NULL DEFAULT NULL,
            MODIFY COLUMN channel ENUM('PUSH','IN_APP','SMS') NOT NULL DEFAULT 'SMS'");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE notification_logs
            MODIFY COLUMN recipient VARCHAR(15) NULL DEFAULT NULL,
            MODIFY COLUMN channel ENUM('SMS','EMAIL') NOT NULL DEFAULT 'SMS'");
    },
];