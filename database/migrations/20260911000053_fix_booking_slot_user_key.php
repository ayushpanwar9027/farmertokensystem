<?php

declare(strict_types=1);

/*
 * The uq_booking_slot_user composite key (slot_id, user_id, status) blocked
 * legitimate flows: once a booking for a (slot, user) was cancelled, any
 * later attempt to cancel again for the same (slot, user) created a
 * duplicate 'CANCELLED' status value and threw a 1062. Harden it so the
 * uniqueness only applies to ACTIVE (PENDING/CONFIRMED) bookings.
 */

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE bookings DROP INDEX uq_booking_slot_user");
        $db->exec("ALTER TABLE bookings
            ADD COLUMN active_slot_user_key VARCHAR(16)
                GENERATED ALWAYS AS (
                    CASE WHEN status IN ('PENDING','CONFIRMED') THEN status ELSE NULL END
                ) STORED");
        $db->exec("ALTER TABLE bookings
            ADD UNIQUE KEY uq_booking_slot_user (slot_id, user_id, active_slot_user_key)");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE bookings DROP INDEX uq_booking_slot_user");
        $db->exec("ALTER TABLE bookings DROP COLUMN active_slot_user_key");
        $db->exec("ALTER TABLE bookings
            ADD UNIQUE KEY uq_booking_slot_user (slot_id, user_id, status)");
    },
];