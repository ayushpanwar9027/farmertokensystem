<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class District extends BaseModel
{
    protected string $table = 'districts';
    protected bool $softDeletes = false;

    public function active(): array
    {
        return Database::select(
            "SELECT id, name, code, state, is_active
             FROM districts
             WHERE is_active = 1
             ORDER BY name"
        );
    }

    public function isActive(int $districtId): bool
    {
        $row = Database::selectOne(
            "SELECT 1 FROM districts WHERE id = ? AND is_active = 1 LIMIT 1",
            [$districtId]
        );
        return $row !== null;
    }
}