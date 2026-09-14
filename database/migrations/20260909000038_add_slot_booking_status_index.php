<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE bookings
            ADD KEY booking_slot_status_idx (slot_id, status)");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE bookings DROP INDEX booking_slot_status_idx");
    },
];
