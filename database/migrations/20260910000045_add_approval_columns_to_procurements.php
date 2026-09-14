<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM procurements")->fetchAll(\PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $cols);

        if (!in_array('accepted_weight', $existing)) {
            $db->exec("ALTER TABLE procurements ADD COLUMN accepted_weight DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER quantity_kg");
        }
        if (!in_array('damaged_qty', $existing)) {
            $db->exec("ALTER TABLE procurements ADD COLUMN damaged_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER accepted_weight");
        }
        if (!in_array('procurement_number', $existing)) {
            $db->exec("ALTER TABLE procurements ADD COLUMN procurement_number VARCHAR(30) NULL DEFAULT NULL AFTER id");
        }
        if (!in_array('operator_note', $existing)) {
            $db->exec("ALTER TABLE procurements ADD COLUMN operator_note VARCHAR(500) NULL DEFAULT NULL AFTER notes");
        }
        if (!in_array('approved_amount', $existing)) {
            $db->exec("ALTER TABLE procurements ADD COLUMN approved_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER amount");
        }
        if (!in_array('approved_by', $existing)) {
            $db->exec("ALTER TABLE procurements ADD COLUMN approved_by INT UNSIGNED NULL DEFAULT NULL AFTER approved_amount");
        }
        if (!in_array('approved_at', $existing)) {
            $db->exec("ALTER TABLE procurements ADD COLUMN approved_at DATETIME NULL DEFAULT NULL AFTER approved_by");
        }

        $statusCol = $db->query("SHOW COLUMNS FROM procurements LIKE 'status'")->fetch(\PDO::FETCH_ASSOC);
        $statusType = strtolower((string) $statusCol['Type']);
        if (strpos($statusType, 'pending_approval') === false) {
            $db->exec("ALTER TABLE procurements MODIFY COLUMN status ENUM('PENDING','PENDING_APPROVAL','VERIFIED','IN_PROGRESS','COMPLETED','REJECTED') NOT NULL DEFAULT 'PENDING'");
        }

        $idxExists = false;
        $indexes = $db->query("SHOW INDEX FROM procurements WHERE Key_name = 'uq_procurement_number'")->fetchAll();
        if (!empty($indexes)) {
            $idxExists = true;
        }
        if (!$idxExists) {
            $db->exec("CREATE UNIQUE INDEX uq_procurement_number ON procurements (procurement_number)");
        }

        $fkExists = false;
        $fks = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'procurements' AND CONSTRAINT_NAME = 'fk_proc_approved_by'")->fetchAll();
        if (!empty($fks)) {
            $fkExists = true;
        }
        if (!$fkExists) {
            $db->exec("ALTER TABLE procurements ADD CONSTRAINT fk_proc_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL");
        }
    },
    'down' => function (\PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM procurements")->fetchAll(\PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $cols);

        if (in_array('procurement_number', $existing)) {
            $db->exec("ALTER TABLE procurements DROP INDEX uq_procurement_number");
            $db->exec("ALTER TABLE procurements DROP COLUMN procurement_number");
        }
        if (in_array('operator_note', $existing)) {
            $db->exec("ALTER TABLE procurements DROP COLUMN operator_note");
        }
        if (in_array('accepted_weight', $existing)) {
            $db->exec("ALTER TABLE procurements DROP COLUMN accepted_weight");
        }
        if (in_array('damaged_qty', $existing)) {
            $db->exec("ALTER TABLE procurements DROP COLUMN damaged_qty");
        }
        if (in_array('approved_by', $existing)) {
            $db->exec("ALTER TABLE procurements DROP FOREIGN KEY fk_proc_approved_by");
            $db->exec("ALTER TABLE procurements DROP COLUMN approved_by");
        }
        if (in_array('approved_amount', $existing)) {
            $db->exec("ALTER TABLE procurements DROP COLUMN approved_amount");
        }
        if (in_array('approved_at', $existing)) {
            $db->exec("ALTER TABLE procurements DROP COLUMN approved_at");
        }
    },
];
