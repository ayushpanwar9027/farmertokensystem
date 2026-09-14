<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\SystemSetting;

class SettingService
{
    private const CACHE_NS = 'settings';
    private const CACHE_TTL = 300;
    private const FRESH_KEYS = ['maintenance_mode'];

    private static array $memoryMap = [];
    private static bool $memoryLoaded = false;

    private CacheService $cache;
    private SecretService $secrets;

    public function __construct()
    {
        $this->cache = new CacheService(self::CACHE_NS);
        $this->secrets = new SecretService();
    }

    public function registry(): array
    {
        return config('settings_defaults', []);
    }

    public function schema(string $key): ?array
    {
        $registry = $this->registry();
        return $registry[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return $key !== '' && $this->schema($key) !== null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        if (in_array($key, self::FRESH_KEYS, true)) {
            return $this->readFresh($key, $default);
        }

        $schema = $this->schema($key);

        if ($schema !== null && !empty($schema['sensitive'])) {
            $secret = $this->secrets->get($this->secretKeyForSetting($key));
            return $secret ?? $default;
        }

        if (array_key_exists($key, self::$memoryMap)) {
            return self::$memoryMap[$key];
        }

        $this->ensureLoaded();

        if (array_key_exists($key, self::$memoryMap)) {
            return self::$memoryMap[$key];
        }

        return $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return $this->toBool($this->get($key, $default));
    }

    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    public function getJson(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }
        return $value;
    }

    public function set(string $key, mixed $value, ?int $actorId = null): mixed
    {
        $schema = $this->schema($key);
        if ($schema === null) {
            throw new ValidationException(['settings' => ['Unknown setting key: ' . $key]]);
        }

        $error = $this->validateValue($key, $value);
        if ($error !== null) {
            throw new ValidationException([$key => [$error]]);
        }

        $type = (string) $schema['type'];
        $sensitive = (bool) ($schema['sensitive'] ?? false);

        if ($sensitive) {
            $this->secrets->set($this->secretKeyForSetting($key), (string) $value, $actorId);
        }

        $storage = $sensitive ? null : $this->toStorage($value, $type);

        (new SystemSetting())->upsertByKey(
            $key,
            $storage,
            $type,
            (int) ($schema['public'] ?? 0),
            $sensitive ? 1 : 0,
            (string) ($schema['group'] ?? 'GENERAL'),
            (string) ($schema['description'] ?? ''),
            $actorId
        );

        $this->invalidateCache();

        $typed = $this->coerce($value, $type);
        self::$memoryMap[$key] = $typed;

        return $typed;
    }

    public function setMany(array $values, ?int $actorId = null): array
    {
        $errors = $this->validateMap($values);
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $updated = [];
        foreach ($values as $key => $value) {
            $updated[(string) $key] = $this->set((string) $key, $value, $actorId);
        }

        return $updated;
    }

    public function validateMap(array $values): array
    {
        $errors = [];
        foreach ($values as $key => $value) {
            $field = (string) $key;
            $error = $this->validateValue($field, $value);
            if ($error !== null) {
                $errors[$field][] = $error;
            }
        }
        return $errors;
    }

    public function validateValue(string $key, mixed $value): ?string
    {
        $schema = $this->schema($key);
        if ($schema === null) {
            return 'Unknown setting key';
        }

        $type = (string) $schema['type'];

        return $this->isValidForType($type, $value) ? null : $this->typeErrorMessage($type);
    }

    public function all(): array
    {
        $registry = $this->registry();
        $rows = (new SystemSetting())->allRows();
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[(string) $row['key_name']] = $row;
        }

        $groups = [];
        $groupOrder = [];

        foreach ($registry as $key => $def) {
            $row = $byKey[$key] ?? null;
            $type = (string) ($row['value_type'] ?? $def['type']);
            $public = $row !== null ? (int) $row['is_public'] : (int) ($def['public'] ?? 0);
            $sensitive = $row !== null ? (int) $row['is_sensitive'] : (int) ($def['sensitive'] ?? 0);
            $group = (string) ($row['group_name'] ?? $def['group'] ?? 'GENERAL');
            $description = (string) ($row['description'] ?? $def['description'] ?? '');

            $entry = [
                'key' => $key,
                'type' => $type,
                'public' => (bool) $public,
                'sensitive' => (bool) $sensitive,
                'description' => $description,
                'group' => $group,
            ];

            if ($sensitive) {
                $entry['exists'] = $this->secrets->exists($this->secretKeyForSetting($key));
            } else {
                $entry['value'] = $this->get($key, $def['default'] ?? null);
            }

            $groups[$group][$key] = $entry;
            if (!in_array($group, $groupOrder, true)) {
                $groupOrder[] = $group;
            }
        }

        $custom = [];
        foreach ($rows as $row) {
            $key = (string) $row['key_name'];
            if (isset($registry[$key])) {
                continue;
            }
            $entry = [
                'key' => $key,
                'type' => (string) $row['value_type'],
                'public' => (bool) $row['is_public'],
                'sensitive' => (bool) $row['is_sensitive'],
                'description' => (string) ($row['description'] ?? ''),
                'group' => 'CUSTOM',
            ];
            if ($entry['sensitive']) {
                $entry['exists'] = $this->secrets->exists($this->secretKeyForSetting($key));
            } else {
                $entry['value'] = $this->get($key, null);
            }
            $custom[$key] = $entry;
        }
        if (!empty($custom)) {
            $groups['CUSTOM'] = $custom;
            $groupOrder[] = 'CUSTOM';
        }

        $result = [];
        foreach ($groupOrder as $group) {
            $result[] = [
                'group' => $group,
                'settings' => array_values($groups[$group]),
            ];
        }

        return $result;
    }

    public function invalidateCache(): void
    {
        self::$memoryMap = [];
        self::$memoryLoaded = false;
        $this->cache->flush();
    }

    public function clearCache(): void
    {
        $this->invalidateCache();
    }

    private function ensureLoaded(): void
    {
        if (self::$memoryLoaded) {
            return;
        }

        $cached = $this->cache->get('map');
        if (is_array($cached)) {
            self::$memoryMap = $cached;
            self::$memoryLoaded = true;
            return;
        }

        self::$memoryMap = $this->buildMap();
        self::$memoryLoaded = true;
        $this->cache->set('map', self::$memoryMap, self::CACHE_TTL);
    }

    private function buildMap(): array
    {
        $map = [];

        foreach ($this->registry() as $key => $def) {
            $map[$key] = $this->coerce($def['default'] ?? null, (string) ($def['type'] ?? 'STRING'));
        }

        foreach ((new SystemSetting())->allRows() as $row) {
            $map[(string) $row['key_name']] = $this->coerce($row['key_value'], (string) $row['value_type']);
        }

        return $map;
    }

    private function readFresh(string $key, mixed $default): mixed
    {
        $row = \App\Core\Database::selectOne(
            "SELECT key_value, value_type FROM system_settings WHERE key_name = ?",
            [$key]
        );

        if ($row === null) {
            return $default;
        }

        return $this->coerce($row['key_value'], (string) $row['value_type']);
    }

    private function coerce(mixed $value, string $type): mixed
    {
        return match ($type) {
            'BOOL' => $this->toBool($value),
            'INT' => is_numeric($value) ? (int) $value : 0,
            'FLOAT' => is_numeric($value) ? (float) $value : 0.0,
            'JSON' => $this->toArray($value),
            default => $value === null ? '' : (string) $value,
        };
    }

    private function toStorage(mixed $value, string $type): string
    {
        return match ($type) {
            'BOOL' => $this->toBool($value) ? '1' : '0',
            'INT' => (string) (int) $value,
            'FLOAT' => (string) (float) $value,
            'JSON' => json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
        }
        return (bool) $value;
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function isValidForType(string $type, mixed $value): bool
    {
        return match ($type) {
            'BOOL' => $this->isBoolLike($value),
            'INT' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1),
            'FLOAT' => is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value))),
            'STRING' => is_scalar($value) && !is_bool($value),
            'JSON' => is_array($value) || (is_string($value) && $this->isValidJsonArray($value)),
            default => true,
        };
    }

    private function isBoolLike(mixed $value): bool
    {
        if (is_bool($value) || is_int($value)) {
            return true;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'], true);
        }
        return false;
    }

    private function isValidJsonArray(string $value): bool
    {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded));
    }

    private function typeErrorMessage(string $type): string
    {
        return match ($type) {
            'BOOL' => 'Value must be a boolean',
            'INT' => 'Value must be an integer',
            'FLOAT' => 'Value must be a number',
            'STRING' => 'Value must be a string',
            'JSON' => 'Value must be a JSON array or object',
            default => 'Value is invalid for this setting',
        };
    }

    private function secretKeyForSetting(string $key): string
    {
        return 'setting_' . $key;
    }
}