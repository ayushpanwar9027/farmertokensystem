<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Permission extends BaseModel
{
    protected string $table = 'permissions';
    protected bool $softDeletes = false;

    public function byName(string $name): ?array
    {
        return $this->findBy('name', $name);
    }

    public function existsByName(string $name): bool
    {
        $row = Database::selectOne(
            "SELECT id FROM permissions WHERE name = ? LIMIT 1",
            [$name]
        );
        return $row !== null;
    }

    public function idByName(string $name): ?int
    {
        $row = Database::selectOne(
            "SELECT id FROM permissions WHERE name = ? LIMIT 1",
            [$name]
        );
        return $row !== null ? (int) $row['id'] : null;
    }

    public function groupedCatalog(): array
    {
        $rows = Database::select(
            "SELECT name, display_name, module, description, is_system
             FROM permissions
             ORDER BY module, name"
        );

        $catalog = [];
        foreach ($rows as $row) {
            $module = $row['module'] ?: 'OTHER';
            if (!isset($catalog[$module])) {
                $catalog[$module] = [];
            }
            $catalog[$module][] = [
                'name' => $row['name'],
                'display_name' => $row['display_name'],
                'description' => $row['description'],
                'is_system' => (bool) $row['is_system'],
            ];
        }

        return $catalog;
    }

    public function allNames(): array
    {
        $rows = Database::select("SELECT name FROM permissions ORDER BY name");
        return array_map(fn($r) => $r['name'], $rows);
    }
}
