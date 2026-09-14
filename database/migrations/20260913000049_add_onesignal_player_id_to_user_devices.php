<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE user_devices
            ADD COLUMN onesignal_player_id VARCHAR(190) NULL DEFAULT NULL AFTER app_version");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE user_devices
            DROP COLUMN onesignal_player_id");
    },
];