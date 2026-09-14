<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\ValidationException;

class SystemSecret extends BaseModel
{
    protected string $table = 'system_secrets';
    protected bool $softDeletes = false;

    public function findByKey(string $key): ?array
    {
        return $this->findBy('key_name', $key);
    }

    public function upsert(string $key, ?string $encryptedValue, ?string $iv, int $isSet, ?int $actorId): int
    {
        if (!is_string($key) || $key === '' || $key === null) {
            throw new ValidationException(['key' => ['A valid secret key is required']]);
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->findByKey($key);

        if ($existing === null) {
            return (int) $this->insert([
                'key_name' => $key,
                'encrypted_value' => (string) $encryptedValue,
                'iv' => (string) $iv,
                'is_set' => $isSet,
                'last_rotated_at' => $isSet ? $now : null,
                'last_updated_by' => $actorId,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Database::update(
            $this->table,
            [
                'encrypted_value' => (string) $encryptedValue,
                'iv' => (string) $iv,
                'is_set' => $isSet,
                'last_rotated_at' => $isSet ? $now : $existing['last_rotated_at'],
                'last_updated_by' => $actorId,
                'updated_at' => $now,
            ],
            'id = ?',
            [(int) $existing['id']]
        );

        return (int) $existing['id'];
    }

    public function allRows(): array
    {
        return Database::select("SELECT * FROM {$this->table} ORDER BY id ASC");
    }
}