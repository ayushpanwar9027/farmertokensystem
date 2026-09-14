<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE queue_entries
            MODIFY COLUMN status ENUM('WAITING','CALLED','IN_PROGRESS','COMPLETED','SKIPPED','NO_SHOW','CANCELLED') NOT NULL DEFAULT 'WAITING',
            ADD COLUMN recall_eligible TINYINT(1) NOT NULL DEFAULT 0 AFTER notes,
            ADD COLUMN recall_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER recall_eligible,
            ADD COLUMN recalled_at DATETIME NULL DEFAULT NULL AFTER recall_count,
            ADD COLUMN no_show_at DATETIME NULL DEFAULT NULL AFTER recalled_at,
            ADD COLUMN no_show_by INT UNSIGNED NULL DEFAULT NULL AFTER no_show_at,
            ADD KEY idx_queue_centre_date_status (centre_id, date, status)");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE queue_entries
            DROP KEY idx_queue_centre_date_status,
            DROP COLUMN no_show_by,
            DROP COLUMN no_show_at,
            DROP COLUMN recalled_at,
            DROP COLUMN recall_count,
            DROP COLUMN recall_eligible,
            MODIFY COLUMN status ENUM('WAITING','CALLED','IN_PROGRESS','COMPLETED','SKIPPED','CANCELLED') NOT NULL DEFAULT 'WAITING'");
    },
];