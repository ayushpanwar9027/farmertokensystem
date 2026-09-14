<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE notification_logs
            ADD COLUMN event_ref VARCHAR(64) NULL DEFAULT NULL AFTER event_type,
            ADD UNIQUE KEY uq_nlog_event_ref (event_ref)");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE notification_logs
            DROP KEY uq_nlog_event_ref,
            DROP COLUMN event_ref");
    },
];