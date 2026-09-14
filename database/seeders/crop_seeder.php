<?php

declare(strict_types=1);

function seedCrops(PDO $pdo): int
{
    $crops = [
        ['WHEAT', 'Wheat', 'गेहूँ', 'CEREALS', 'kg', 1, 1],
        ['PADDY', 'Paddy', 'धान', 'CEREALS', 'kg', 1, 1],
        ['RICE', 'Rice', 'चावल', 'CEREALS', 'kg', 1, 0],
        ['MAIZE', 'Maize', 'मक्का', 'CEREALS', 'kg', 1, 0],
        ['BAJRA', 'Bajra', 'बाजरा', 'MILLETS', 'kg', 1, 0],
        ['JOWAR', 'Jowar', 'ज्वार', 'MILLETS', 'kg', 1, 0],
        ['RAGI', 'Ragi', 'रागी', 'MILLETS', 'kg', 1, 0],
        ['TUR_DAL', 'Tur (Arhar) Dal', 'अरहर दाल', 'PULSES', 'kg', 1, 0],
        ['MOONG_DAL', 'Moong Dal', 'मूंग दाल', 'PULSES', 'kg', 1, 0],
        ['CHANA', 'Chana (Gram)', 'चना', 'PULSES', 'kg', 1, 0],
        ['GROUNDNUT', 'Groundnut', 'मूंगफली', 'OILSEEDS', 'kg', 1, 0],
        ['MUSTARD', 'Mustard', 'सरसों', 'OILSEEDS', 'kg', 1, 0],
        ['SUGARCANE', 'Sugarcane', 'गन्ना', 'COMMERCIAL', 'quintal', 1, 0],
        ['COTTON', 'Cotton', 'कपास', 'COMMERCIAL', 'quintal', 1, 0],
        ['ONION', 'Onion', 'प्याज़', 'VEGETABLES', 'kg', 1, 0],
        ['POTATO', 'Potato', 'आलू', 'VEGETABLES', 'kg', 1, 0],
    ];

    $count = 0;
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO crops (code, name, name_hi, category, unit, is_active, is_default)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($crops as $c) {
        $stmt->execute($c);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }

    return $count;
}
