<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE tokens
            ADD COLUMN token_hash VARCHAR(64) NULL DEFAULT NULL AFTER token_number,
            ADD UNIQUE KEY uq_token_hash (token_hash)");
        $db->exec("ALTER TABLE tokens
            MODIFY COLUMN `status` ENUM('ACTIVE','USED','EXPIRED','REVOKED','CANCELLED') NOT NULL DEFAULT 'ACTIVE'");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE tokens
            MODIFY COLUMN `status` ENUM('ACTIVE','USED','EXPIRED','REVOKED') NOT NULL DEFAULT 'ACTIVE'");
        $db->exec("ALTER TABLE tokens
            DROP INDEX uq_token_hash,
            DROP COLUMN token_hash");
    },
];
