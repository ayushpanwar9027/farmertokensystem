<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM payments")->fetchAll(\PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $cols);

        $statusCol = $db->query("SHOW COLUMNS FROM payments LIKE 'status'")->fetch(\PDO::FETCH_ASSOC);
        $statusType = strtolower((string) ($statusCol['Type'] ?? ''));
        if (strpos($statusType, 'initiated') === false) {
            $db->exec("ALTER TABLE payments MODIFY COLUMN status ENUM('PENDING','INITIATED','RELEASED','CANCELLED','REVERSED') NOT NULL DEFAULT 'PENDING'");
        }

        if (!in_array('rate_per_kg', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN rate_per_kg DECIMAL(12,2) NULL DEFAULT NULL AFTER amount");
        }
        if (!in_array('crop_name', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN crop_name VARCHAR(100) NULL DEFAULT NULL AFTER rate_per_kg");
        }
        if (!in_array('reversal_reason', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN reversal_reason VARCHAR(500) NULL DEFAULT NULL AFTER failure_reason");
        }
        if (!in_array('reversed_by', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN reversed_by INT UNSIGNED NULL DEFAULT NULL AFTER processed_by");
        }
        if (!in_array('reversed_at', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN reversed_at DATETIME NULL DEFAULT NULL AFTER reversed_by");
        }
        if (!in_array('cancelled_by', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN cancelled_by INT UNSIGNED NULL DEFAULT NULL AFTER reversed_at");
        }
        if (!in_array('cancelled_at', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN cancelled_at DATETIME NULL DEFAULT NULL AFTER cancelled_by");
        }
        if (!in_array('initiated_at', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN initiated_at DATETIME NULL DEFAULT NULL AFTER cancelled_at");
        }
        if (!in_array('initiated_by', $existing)) {
            $db->exec("ALTER TABLE payments ADD COLUMN initiated_by INT UNSIGNED NULL DEFAULT NULL AFTER initiated_at");
        }

        $payIdxExists = false;
        $payIndexes = $db->query("SHOW INDEX FROM payments WHERE Key_name = 'uq_payment_procurement'")->fetchAll();
        if (!empty($payIndexes)) {
            $payIdxExists = true;
        }
        if (!$payIdxExists) {
            $db->exec("CREATE UNIQUE INDEX uq_payment_procurement ON payments (procurement_id)");
        }

        $db->exec("CREATE TABLE IF NOT EXISTS crop_rates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            crop_id INT UNSIGNED NOT NULL,
            centre_id INT UNSIGNED NULL DEFAULT NULL,
            district_id INT UNSIGNED NULL DEFAULT NULL,
            rate_per_kg DECIMAL(12,2) NOT NULL,
            effective_from DATE NOT NULL,
            effective_to DATE NULL DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_crop_rate_crop (crop_id),
            KEY idx_crop_rate_centre (centre_id),
            KEY idx_crop_rate_active (crop_id, centre_id, is_active, effective_from),
            CONSTRAINT fk_crate_crop FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE RESTRICT,
            CONSTRAINT fk_crate_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS crop_rates");

        $cols = $db->query("SHOW COLUMNS FROM payments")->fetchAll(\PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $cols);

        if (in_array('initiated_by', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN initiated_by");
        }
        if (in_array('initiated_at', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN initiated_at");
        }
        if (in_array('cancelled_at', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN cancelled_at");
        }
        if (in_array('cancelled_by', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN cancelled_by");
        }
        if (in_array('reversed_at', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN reversed_at");
        }
        if (in_array('reversed_by', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN reversed_by");
        }
        if (in_array('reversal_reason', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN reversal_reason");
        }
        if (in_array('crop_name', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN crop_name");
        }
        if (in_array('rate_per_kg', $existing)) {
            $db->exec("ALTER TABLE payments DROP COLUMN rate_per_kg");
        }

        $db->exec("ALTER TABLE payments DROP INDEX uq_payment_procurement");

        $db->exec("ALTER TABLE payments MODIFY COLUMN status ENUM('PENDING','PROCESSING','PAID','FAILED') NOT NULL DEFAULT 'PENDING'");
    },
];
