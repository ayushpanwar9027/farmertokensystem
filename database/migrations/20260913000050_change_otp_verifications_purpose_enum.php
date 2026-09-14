<?php

declare(strict_types=1);

return [
    'up' => function (\PDO $db) {
        $db->exec("UPDATE otp_verifications SET purpose = 'REGISTER' WHERE purpose = 'REGISTRATION'");
        $db->exec("ALTER TABLE otp_verifications
            MODIFY COLUMN purpose ENUM('REGISTER','LOGIN','LOGIN_2FA','PASSWORD_RESET','MOBILE_CHANGE','2FA_ENABLE','2FA_STEP_UP') NOT NULL");
    },
    'down' => function (\PDO $db) {
        $db->exec("ALTER TABLE otp_verifications
            MODIFY COLUMN purpose ENUM('REGISTRATION','LOGIN','PASSWORD_RESET') NOT NULL");
        $db->exec("UPDATE otp_verifications SET purpose = 'REGISTRATION' WHERE purpose = 'REGISTER'");
    },
];