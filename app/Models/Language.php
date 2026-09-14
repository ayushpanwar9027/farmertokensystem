<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Language extends BaseModel
{
    protected string $table = 'languages';

    public function idByCode(string $code): ?int
    {
        $row = Database::selectOne("SELECT id FROM languages WHERE code = ? LIMIT 1", [$code]);
        return $row !== null ? (int) $row['id'] : null;
    }

    public function findByCode(string $code): ?array
    {
        return $this->findBy('code', $code);
    }

    public function enabledCodes(): array
    {
        $rows = Database::select("SELECT code FROM languages WHERE is_enabled = 1 ORDER BY is_default DESC, id ASC");
        return array_map(fn($r) => (string) $r['code'], $rows);
    }
}