<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Crop extends BaseModel
{
    protected string $table = 'crops';
    protected bool $softDeletes = true;

    public function byCode(string $code): ?array
    {
        return Database::selectOne(
            "SELECT * FROM crops WHERE code = ? AND deleted_at IS NULL LIMIT 1",
            [$code]
        ) ?: null;
    }

    public function activeCatalog(): array
    {
        return Database::select(
            "SELECT * FROM crops WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC"
        );
    }
}
