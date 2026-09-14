<?php

declare(strict_types=1);

function seedNotificationTemplates(PDO $pdo): int
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_key VARCHAR(30) NOT NULL,
        title_en VARCHAR(190) NOT NULL,
        title_hi VARCHAR(190) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_notif_tpl_event (event_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $templates = [
        ['event_key' => 'booking_confirmed',          'title_en' => 'Booking Confirmed',    'title_hi' => 'बुकिंग की पुष्टि'],
        ['event_key' => 'booking_cancelled',           'title_en' => 'Booking Cancelled',    'title_hi' => 'बुकिंग रद्द'],
        ['event_key' => 'verification_approved',       'title_en' => 'Registration Approved','title_hi' => 'पंजीकरण स्वीकृत'],
        ['event_key' => 'verification_rejected',       'title_en' => 'Registration Rejected','title_hi' => 'पंजीकरण अस्वीकृत'],
        ['event_key' => 'queue_approaching',           'title_en' => 'Queue Update',         'title_hi' => 'कतार अपडेट'],
        ['event_key' => 'farmer_called',               'title_en' => 'You Are Called',       'title_hi' => 'आपको बुलाया गया है'],
        ['event_key' => 'procurement_completed',       'title_en' => 'Procurement Completed','title_hi' => 'प्रक्रिया पूर्ण'],
        ['event_key' => 'payment_processing',          'title_en' => 'Payment Processing',  'title_hi' => 'भुगतान प्रक्रिया'],
        ['event_key' => 'payment_paid',                'title_en' => 'Payment Received',    'title_hi' => 'भुगतान प्राप्त'],
        ['event_key' => 'payment_failed',              'title_en' => 'Payment Failed',      'title_hi' => 'भुगतान विफल'],
    ];

    $count = 0;
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO notification_templates (event_key, title_en, title_hi)
         VALUES (?, ?, ?)"
    );

    foreach ($templates as $tpl) {
        $stmt->execute([$tpl['event_key'], $tpl['title_en'], $tpl['title_hi']]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
