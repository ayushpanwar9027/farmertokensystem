<?php

declare(strict_types=1);

/*
 * Booking numbers ("BK-YYYYMMDD-NNNNN") were allocated via
 *   SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = ?
 * which is NOT atomic: concurrent requests all read the same count and
 * generate the SAME sequence number. Exactly one INSERT then succeeds on the
 * unique booking_number key and every other gets a spurious 1062 / 409
 * DUPLICATE_BOOKING — even for different users on the same slot.
 *
 * Replace it with a small per-day sequence table bumped atomically.
 */

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE IF NOT EXISTS booking_sequences (
            date_key DATE NOT NULL,
            seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (date_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed from the current per-day booking counts so already-issued
        // booking numbers are not re-allocated. seq = count issued so far;
        // the next allocation returns count + 1.
        $db->exec("INSERT INTO booking_sequences (date_key, seq)
            SELECT DATE(created_at), COUNT(*) AS c
            FROM bookings
            WHERE deleted_at IS NULL
            GROUP BY DATE(created_at)
            ON DUPLICATE KEY UPDATE seq = seq");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS booking_sequences");
    },
];