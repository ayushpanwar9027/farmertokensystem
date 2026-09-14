<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE rate_limit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            limiter_key VARCHAR(190) NOT NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            window_start DATETIME NOT NULL,
            window_end DATETIME NOT NULL,
            last_hit_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rl_key_window (limiter_key, window_start),
            KEY idx_rl_window_end (window_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS rate_limit_logs");
    },
];