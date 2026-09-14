<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Translation extends BaseModel
{
    protected string $table = 'translations';

    public function keyedByLocale(string $code): array
    {
        $rows = Database::select(
            "SELECT t.translation_key, t.translated_value
             FROM translations t
             INNER JOIN languages l ON l.id = t.language_id
             WHERE l.code = ?",
            [$code]
        );

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['translation_key']] = $row['translated_value'];
        }
        return $keyed;
    }

    public function countByLocale(string $code): int
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM translations t
             INNER JOIN languages l ON l.id = t.language_id
             WHERE l.code = ?",
            [$code]
        );
        return $row !== null ? (int) $row['total'] : 0;
    }
}