<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $body;
    private array $query;
    private array $routeParams = [];
    private array $headers;
    private string $method;
    private string $uri;
    private string $requestId;
    private ?array $user = null;
    private array $routeMiddleware = [];

    private string $locale = '';

    private static string $currentRequestId = '';
    private static string $currentLocale = '';

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->parseUri();
        $this->query = $_GET;
        $this->headers = $this->parseHeaders();
        $this->body = $this->parseBody();
        $this->requestId = $this->generateRequestId();
        self::$currentRequestId = $this->requestId;
    }

    public static function currentRequestId(): string
    {
        if (self::$currentRequestId === '') {
            self::$currentRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? 'req_' . bin2hex(random_bytes(4));
        }
        return self::$currentRequestId;
    }

    public static function setLocale(string $locale): void
    {
        self::$currentLocale = $locale;
    }

    public static function currentLocale(): string
    {
        if (self::$currentLocale !== '') {
            return self::$currentLocale;
        }
        return (string) config('locales.default', 'en');
    }

    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        return rtrim($uri, '/') ?: '/';
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'HTTP_', 5) === 0) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    private function parseBody(): array
    {
        $contentType = $this->headers['content-type'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            return $_POST;
        }
        return [];
    }

    private function generateRequestId(): string
    {
        $header = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        if ($header !== '' && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $header)) {
            return $header;
        }
        return 'req_' . bin2hex(random_bytes(4));
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        return $this->uri;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }
        return $result;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function header(string $key, $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function getLocale(): string
    {
        return $this->locale !== '' ? $this->locale : self::currentLocale();
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function getParam(string $key, $default = null)
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getBearerToken(): ?string
    {
        $header = $this->header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    public function getUserId(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }

    public function getRoleId(): ?int
    {
        return isset($this->user['role_id']) ? (int) $this->user['role_id'] : null;
    }

    public function setRouteMiddleware(array $middleware): void
    {
        $this->routeMiddleware = $middleware;
    }

    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }
}
