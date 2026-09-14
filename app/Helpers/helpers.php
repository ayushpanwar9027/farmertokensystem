<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);
        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = base_path('app');
        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        $base = base_path('config');
        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = base_path('storage');
        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        $base = base_path('public');
        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }
}

if (!function_exists('is_writable_recursive')) {
    function is_writable_recursive(string $path): bool
    {
        if (!is_dir($path)) {
            return is_writable($path);
        }

        if (!is_writable($path)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        if (!function_exists('get_setting')) {
            return $default;
        }

        return get_setting($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        $path = config_path($file . '.php');
        if (!file_exists($path)) {
            return $default;
        }

        $config = require $path;
        $value = $config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('request_id')) {
    function request_id(): string
    {
        return \App\Core\Request::currentRequestId();
    }
}

if (!function_exists('generate_token')) {
    function generate_token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

if (!function_exists('hash_value')) {
    function hash_value(string $value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verify_hash')) {
    function verify_hash(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }
}

if (!function_exists('paginate')) {
    function paginate(array $data, int $total, int $page, int $perPage): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, $page);

        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'next_page' => $page < $totalPages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null,
        ];
    }
}

if (!function_exists('get_setting')) {
    function get_setting(string $key, mixed $default = null): mixed
    {
        $service = new \App\Services\SettingService();
        return $service->get($key, $default);
    }
}
