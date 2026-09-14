<?php

declare(strict_types=1);

function seedCropRates(PDO $pdo): int
{
    $rates = [
        'WHEAT' => 22.50,
        'PADDY' => 20.40,
        'RICE' => 24.00,
        'MAIZE' => 21.00,
        'BAJRA' => 18.50,
        'JOWAR' => 19.00,
        'RAGI' => 26.50,
        'TUR_DAL' => 68.00,
        'MOONG_DAL' => 78.00,
        'CHANA' => 52.00,
        'GROUNDNUT' => 58.00,
        'MUSTARD' => 53.50,
        'SUGARCANE' => 3.40,
        'COTTON' => 65.00,
        'ONION' => 24.00,
        'POTATO' => 14.50,
    ];

    $today = date('Y-m-d');
    $count = 0;

    foreach ($rates as $code => $rate) {
        $row = $pdo->prepare("SELECT id FROM crops WHERE code = ? LIMIT 1");
        $row->execute([$code]);
        $crop = $row->fetch();

        if ($crop === false) {
            continue;
        }

        $exists = $pdo->prepare(
            "SELECT id FROM crop_rates WHERE crop_id = ? AND centre_id IS NULL AND is_active = 1 LIMIT 1"
        );
        $exists->execute([(int) $crop['id']]);
        if ($exists->fetch() !== false) {
            continue;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO crop_rates (crop_id, centre_id, rate_per_kg, effective_from, is_active)
             VALUES (?, NULL, ?, ?, 1)"
        );
        $stmt->execute([(int) $crop['id'], $rate, $today]);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}