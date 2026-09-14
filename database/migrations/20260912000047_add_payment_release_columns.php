<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM payments")->fetchAll(\PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $cols);

        if (!in_array('payment_reference', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN payment_reference VARCHAR(100) NULL DEFAULT NULL AFTER reference");
        }
        if (!in_array('released_by', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN released_by INT UNSIGNED NULL DEFAULT NULL AFTER processed_by");
        }
        if (!in_array('released_at', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN released_at DATETIME NULL DEFAULT NULL AFTER released_by");
        }
        if (!in_array('booking_crop_id', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN booking_crop_id INT UNSIGNED NULL DEFAULT NULL AFTER procurement_id");
        }
        if (!in_array('attempts', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER released_at");
        }
    },
    'down' => function (\PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM payments")->fetchAll(\PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $cols);

        if (in_array('attempts', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN attempts");
        }
        if (in_array('booking_crop_id', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN booking_crop_id");
        }
        if (in_array('released_at', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN released_at");
        }
        if (in_array('released_by', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN released_by");
        }
        if (in_array('payment_reference', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN payment_reference");
        }
    },
];