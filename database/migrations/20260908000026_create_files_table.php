<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("CREATE TABLE files (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(190) NOT NULL,
            source_type ENUM('LOCAL','URL','EXTERNAL') NOT NULL DEFAULT 'LOCAL',
            path_or_url VARCHAR(500) NOT NULL,
            folder_id INT UNSIGNED NULL DEFAULT NULL,
            mime_type VARCHAR(100) NULL DEFAULT NULL,
            size INT UNSIGNED NOT NULL DEFAULT 0,
            extension VARCHAR(20) NULL DEFAULT NULL,
            checksum VARCHAR(64) NULL DEFAULT NULL,
            created_by INT UNSIGNED NULL DEFAULT NULL,
            status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_file_folder (folder_id),
            KEY idx_file_type (source_type),
            CONSTRAINT fk_file_folder FOREIGN KEY (folder_id) REFERENCES file_folders(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (\PDO $db) {
        $db->exec("DROP TABLE IF EXISTS files");
    },
];
