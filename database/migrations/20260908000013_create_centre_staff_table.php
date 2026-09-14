<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE centre_staff (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            centre_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role ENUM('CENTRE_MANAGER','CENTRE_OPERATOR') NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_centre_staff_centre (centre_id),
            UNIQUE KEY uq_centre_staff_user_role (user_id, role),
            CONSTRAINT fk_cs_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE CASCADE,
            CONSTRAINT fk_cs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS centre_staff");
    },
];
