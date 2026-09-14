<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("ALTER TABLE booking_crops
            ADD COLUMN crop_id INT UNSIGNED NULL DEFAULT NULL AFTER booking_id,
            ADD COLUMN `status` ENUM('PENDING','CANCELLED') NOT NULL DEFAULT 'PENDING' AFTER notes,
            ADD KEY idx_bc_crop (crop_id)");
        $db->exec("ALTER TABLE booking_crops
            ADD CONSTRAINT fk_bc_crop FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE booking_crops
            DROP FOREIGN KEY fk_bc_crop");
        $db->exec("ALTER TABLE booking_crops
            DROP INDEX idx_bc_crop,
            DROP COLUMN crop_id,
            DROP COLUMN `status`");
    },
];
