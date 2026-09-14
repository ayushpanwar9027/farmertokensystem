<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE files
            ADD COLUMN `scope` ENUM('system','user','centre') NOT NULL DEFAULT 'user' AFTER extension,
            ADD KEY idx_files_scope (scope)");
        $db->exec("ALTER TABLE file_folders
            ADD COLUMN `scope` ENUM('system','user','centre') NOT NULL DEFAULT 'user' AFTER parent_id");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE files
            DROP INDEX idx_files_scope,
            DROP COLUMN `scope`");
        $db->exec("ALTER TABLE file_folders
            DROP COLUMN `scope`");
    },
];