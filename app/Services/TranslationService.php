<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Models\Language;
use App\Models\Translation;

class TranslationService
{
    private const KEY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,189}$/';

    private static array $missingLogLast = [];

    private LocalizationService $localization;
    private CacheService $cache;

    public function __construct()
    {
        $this->localization = new LocalizationService();
        $this->cache = new CacheService('l10n');
    }

    public function packFor(string $locale): array
    {
        $locale = $this->resolveLocale($locale);

        if ($this->localization->defaultLocale() === $locale) {
            $key = 'pack_' . $locale;
        } else {
            $key = 'pack_' . $locale;
        }

        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $fallback = $this->localization->fallbackLocale();
        $pack = $fallback !== $locale
            ? array_replace($this->rawPack($fallback), $this->rawPack($locale))
            : $this->rawPack($locale);

        $this->cache->set($key, $pack);

        return $pack;
    }

    public function rawPack(string $locale): array
    {
        $locale = $this->resolveLocale($locale);
        $key = 'raw_' . $locale;

        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $file = base_path('resources/strings/' . $locale . '.php');
        $pack = is_file($file) ? (array) require $file : [];

        $dbRows = (new Translation())->keyedByLocale($locale);
        $pack = array_replace($pack, $dbRows);

        $this->cache->set($key, $pack);

        return $pack;
    }

    public function translate(string $locale, string $key, array $params = []): string
    {
        $locale = $this->resolveLocale($locale);
        $pack = $this->packFor($locale);

        $value = array_key_exists($key, $pack) ? $pack[$key] : null;

        if ($value === null && $this->localization->fallbackLocale() !== $locale) {
            $fallback = $this->packFor($this->localization->fallbackLocale());
            $value = array_key_exists($key, $fallback) ? $fallback[$key] : null;
        }

        if ($value === null) {
            $this->logMissing($locale, $key);
            return $key;
        }

        if (!empty($params)) {
            $replace = [];
            foreach ($params as $k => $v) {
                $replace['{' . $k . '}'] = (string) $v;
                $replace[':' . $k] = (string) $v;
            }
            $value = strtr((string) $value, $replace);
        }

        return (string) $value;
    }

    public function languages(): array
    {
        $cached = $this->cache->get('langs');
        if (is_array($cached)) {
            return $cached;
        }
        $languages = $this->localization->languageList();
        $this->cache->set('langs', $languages);
        return $languages;
    }

    public function listForAdmin(string $locale, string $search = '', int $page = 1, int $perPage = 50): array
    {
        $locale = $this->resolveLocale($locale);
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = "l.code = ?";
        $params = [$locale];
        if ($search !== '') {
            $where .= " AND t.translation_key LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS c
             FROM translations t
             INNER JOIN languages l ON l.id = t.language_id
             WHERE {$where}",
            $params
        );
        $total = $totalRow !== null ? (int) $totalRow['c'] : 0;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $items = Database::select(
            "SELECT t.id, t.translation_key, t.translated_value, t.updated_by, t.created_at, t.updated_at
             FROM translations t
             INNER JOIN languages l ON l.id = t.language_id
             WHERE {$where}
             ORDER BY t.translation_key ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => array_map(function (array $row) {
                return [
                    'id' => (int) $row['id'],
                    'key' => $row['translation_key'],
                    'value' => $row['translated_value'],
                    'updated_by' => $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }, $items),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ];
    }

    public function bulkUpsert(string $locale, array $translations, int $updatedBy): array
    {
        $locale = $this->resolveLocale($locale);
        $languageId = (new Language())->idByCode($locale);
        if ($languageId === null) {
            throw new \App\Exceptions\NotFoundException('INVALID_LOCALE', 'Locale not found');
        }

        $updated = 0;
        $invalid = 0;
        $skipped = 0;

        Database::beginTransaction();
        try {
            $stmt = Database::getConnection()->prepare(
                "INSERT INTO translations (language_id, translation_key, translated_value, updated_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    translated_value = VALUES(translated_value),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP"
            );

            foreach ($translations as $key => $value) {
                $key = trim((string) $key);
                if (!preg_match(self::KEY_PATTERN, $key)) {
                    $invalid++;
                    continue;
                }
                if (!is_string($value) || trim($value) === '') {
                    $skipped++;
                    continue;
                }
                $stmt->execute([$languageId, $key, trim($value), $updatedBy]);
                $updated++;
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $this->invalidate();

        return [
            'locale' => $locale,
            'updated' => $updated,
            'skipped' => $skipped,
            'invalid' => $invalid,
        ];
    }

    public function import(array $translations, string $locale, int $updatedBy): array
    {
        return $this->bulkUpsert($locale, $translations, $updatedBy);
    }

    public function export(string $locale): array
    {
        $locale = $this->resolveLocale($locale);
        return [
            'locale' => $locale,
            'translations' => $this->rawPack($locale),
            'count' => count($this->rawPack($locale)),
        ];
    }

    public function invalidate(): void
    {
        foreach ($this->localization->activeLocales() as $locale) {
            $this->cache->delete('raw_' . $locale);
            $this->cache->delete('pack_' . $locale);
        }
        $this->cache->delete('langs');
    }

    private function resolveLocale(string $locale): string
    {
        $normalized = $this->localization->normalize($locale);
        if ($this->localization->isSupported($normalized)) {
            return $normalized;
        }
        return $this->localization->defaultLocale();
    }

    private function logMissing(string $locale, string $key): void
    {
        $now = time();
        $bucket = $locale . '|' . $key;
        if (isset(self::$missingLogLast[$bucket]) && ($now - self::$missingLogLast[$bucket]) < 60) {
            return;
        }
        self::$missingLogLast[$bucket] = $now;

        $logPath = storage_path('logs/translations_missing.log');
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logPath,
            json_encode([
                'timestamp' => gmdate('c'),
                'request_id' => Request::currentRequestId(),
                'level' => 'WARN',
                'type' => 'translation_key_missing',
                'locale' => $locale,
                'key' => $key,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}