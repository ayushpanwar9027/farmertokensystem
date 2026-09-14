<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class LocalizationService
{
    private static ?array $userLocaleColumns = null;

    public function activeLocales(): array
    {
        $configLocales = (array) config('locales.active', ['en']);
        try {
            $codes = (new \App\Models\Language())->enabledCodes();
            if (!empty($codes)) {
                return array_values(array_unique($codes));
            }
        } catch (\Throwable $e) {
            // DB unavailable — fall back to configured locales.
        }
        return $configLocales;
    }

    public function defaultLocale(): string
    {
        return (string) config('locales.default', 'en');
    }

    public function fallbackLocale(): string
    {
        return (string) config('locales.fallback', 'en');
    }

    public function normalize(string $locale): string
    {
        $locale = strtolower(trim($locale));
        $locale = str_replace('_', '-', $locale);
        $first = explode('-', $locale)[0];
        return $first === '' ? 'en' : $first;
    }

    public function isSupported(string $locale): bool
    {
        return in_array($this->normalize($locale), $this->activeLocales(), true);
    }

    public function preferredLocale(?string $xLocale, ?string $acceptLanguage, ?array $user): string
    {
        if ($xLocale !== null && $xLocale !== '' && $this->isSupported($xLocale)) {
            return $this->normalize($xLocale);
        }

        if ($acceptLanguage !== null && $acceptLanguage !== '') {
            $parsed = $this->parseAcceptLanguage($acceptLanguage);
            foreach ($parsed as $locale) {
                if ($this->isSupported($locale)) {
                    return $this->normalize($locale);
                }
            }
        }

        if ($user !== null) {
            $saved = $this->userLocale((int) $user['id']);
            if ($saved !== null && $this->isSupported($saved)) {
                return $this->normalize($saved);
            }
        }

        return $this->defaultLocale();
    }

    public function userLocale(int $userId): ?string
    {
        if (!$this->hasColumn('users', 'locale')) {
            return null;
        }
        $row = Database::selectOne("SELECT locale FROM users WHERE id = ? LIMIT 1", [$userId]);
        $locale = $row['locale'] ?? null;
        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    public function hasColumn(string $table, string $column): bool
    {
        if (self::$userLocaleColumns === null) {
            self::$userLocaleColumns = [];
            try {
                $rows = Database::select(
                    "SELECT DISTINCT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()"
                );
                foreach ($rows as $row) {
                    self::$userLocaleColumns[$row['COLUMN_NAME']] = true;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }
        return isset(self::$userLocaleColumns[$table . '.' . $column]);
    }

    public function languageList(): array
    {
        $rows = Database::select(
            "SELECT code, name, native_name, is_default, is_enabled FROM languages ORDER BY is_default DESC, id ASC"
        );
        return array_map(function (array $row) {
            return [
                'code' => $row['code'],
                'name' => $row['name'],
                'native_name' => $row['native_name'],
                'is_default' => (int) $row['is_default'] === 1,
                'is_enabled' => (int) $row['is_enabled'] === 1,
            ];
        }, $rows);
    }

    private function parseAcceptLanguage(string $header): array
    {
        $parts = explode(',', $header);
        $prefs = [];
        foreach ($parts as $part) {
            $segments = array_map('trim', explode(';', $part));
            $q = 1.0;
            foreach (array_slice($segments, 1) as $attr) {
                if (preg_match('/^q=([\d.]+)$/i', $attr, $m)) {
                    $q = (float) $m[1];
                }
            }
            $lang = $this->normalize($segments[0]);
            if ($q > 0) {
                $prefs[] = ['locale' => $lang, 'q' => $q];
            }
        }
        usort($prefs, fn($a, $b) => $b['q'] <=> $a['q']);
        return array_map(fn($p) => $p['locale'], $prefs);
    }
}