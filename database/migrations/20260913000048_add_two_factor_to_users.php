<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE users
            ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mobile_verified_at,
            ADD COLUMN two_factor_enabled_at DATETIME NULL DEFAULT NULL AFTER two_factor_enabled");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE users
            DROP COLUMN two_factor_enabled_at,
            DROP COLUMN two_factor_enabled");
    },
];