<?php

declare(strict_types=1);

function seedSecrets(PDO $pdo): int
{
    $secrets = [
        ['key' => 'onesignal_app_id',       'desc' => 'OneSignal app ID (push)'],
        ['key' => 'onesignal_rest_api_key', 'desc' => 'OneSignal REST API key (push)'],
        ['key' => 'otp_api_key',            'desc' => 'OTP gateway API key (SMS)'],
        ['key' => 'otp_sender_id',          'desc' => 'OTP gateway sender ID'],
        ['key' => 'otp_template_id',        'desc' => 'OTP gateway template ID'],
        ['key' => 'jwt_secret',             'desc' => 'Secret key for JWT token signing'],
        ['key' => 'encryption_key_bootstrap','desc' => 'Bootstrap encryption key (managed via env)'],
    ];

    $placeholderValue = base64_encode('PLACEHOLDER_NOT_SET');
    $placeholderIv = base64_encode('0000000000000000');

    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_secrets (key_name, encrypted_value, iv, is_set, description) VALUES (?, ?, ?, 0, ?)");

    foreach ($secrets as $s) {
        $stmt->execute([$s['key'], $placeholderValue, $placeholderIv, $s['desc']]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
