<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class SystemSetting extends BaseModel
{
    protected string $table = 'system_settings';
    protected bool $softDeletes = false;

    public function findByKey(string $key): ?array
    {
        return $this->findBy('key_name', $key);
    }

    public function updateByKey(string $key, array $data): int
    {
        $existing = $this->findByKey($key);
        if ($existing === null) {
            return 0;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return Database::update(
            $this->table,
            $data,
            'id = ?',
            [(int) $existing['id']]
        );
    }

    public function upsertByKey(
        string $key,
        ?string $value,
        string $type,
        int $public,
        int $sensitive,
        string $group,
        ?string $description,
        ?int $updatedBy
    ): int {
        $now = date('Y-m-d H:i:s');
        $existing = $this->findByKey($key);

        $data = [
            'key_value' => $value,
            'value_type' => $type,
            'is_public' => $public,
            'is_sensitive' => $sensitive,
            'group_name' => $group,
            'description' => $description,
            'updated_by' => $updatedBy,
            'updated_at' => $now,
        ];

        if ($existing === null) {
            $data['key_name'] = $key;
            $data['created_at'] = $now;

            return (int) $this->insert($data);
        }

        Database::update($this->table, $data, 'id = ?', [(int) $existing['id']]);

        return (int) $existing['id'];
    }

    public function allRows(): array
    {
        return Database::select(
            "SELECT key_name, key_value, value_type, is_public, is_sensitive, group_name, description
             FROM {$this->table}
             ORDER BY id ASC"
        );
    }
}