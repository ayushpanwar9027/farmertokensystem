<?php

declare(strict_types=1);

namespace App\Services;

class CacheService
{
    private const FILE_EXT = '.cache.json';

    private string $namespace;
    private string $file;
    private ?array $data = null;

    public function __construct(string $namespace = 'default')
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $namespace);
        $this->namespace = ($clean === null || $clean === '') ? 'default' : $clean;

        $dir = storage_path('cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->file = $dir . DIRECTORY_SEPARATOR . $this->namespace . self::FILE_EXT;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->load();

        if (!array_key_exists($key, $data)) {
            return $default;
        }

        $entry = $data[$key];
        $expiresAt = isset($entry['expires_at']) ? (int) $entry['expires_at'] : 0;

        if ($expiresAt === 0 || time() < $expiresAt) {
            if (array_key_exists('value', $entry)) {
                return $entry['value'];
            }
        }

        unset($data[$key]);
        $this->save($data);

        return $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $data = $this->load();
        $data[$key] = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ];
        $this->save($data);
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function delete(string $key): void
    {
        $data = $this->load();
        if (array_key_exists($key, $data)) {
            unset($data[$key]);
            $this->save($data);
        }
    }

    public function all(): array
    {
        $data = $this->load();
        $values = [];
        foreach ($data as $key => $entry) {
            $values[$key] = array_key_exists('value', $entry) ? $entry['value'] : null;
        }
        return $values;
    }

    public function flush(): void
    {
        $this->data = [];
        if (file_exists($this->file)) {
            @unlink($this->file);
        }
    }

    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        if (file_exists($this->file)) {
            $raw = @file_get_contents($this->file);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $this->data = $decoded;
                return $this->data;
            }
        }

        $this->data = [];

        return $this->data;
    }

    private function save(array $data): void
    {
        $this->data = $data;

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @rename($tmp, $this->file);
        }
    }
}