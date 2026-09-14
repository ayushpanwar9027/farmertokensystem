<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, $handler, array $middleware = []): void
    {
        $this->addRoute($method, $path, $handler, $middleware);
    }

    public function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function match(string $method, string $uri): ?array
    {
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPath($route['path'], $uri);
            if ($params !== false) {
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => $route['middleware'],
                ];
            }
        }

        return null;
    }

    public function group(array $middleware, callable $register): void
    {
        $before = $this->routes;
        $register($this);
        $added = array_slice($this->routes, count($before));

        foreach ($added as &$route) {
            $route['middleware'] = array_merge($middleware, $route['middleware']);
        }
        unset($route);

        $this->routes = array_merge($before, $added);
    }

    private function matchPath(string $pattern, string $uri): array|false
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($patternParts) !== count($uriParts)) {
            return false;
        }

        $params = [];

        foreach ($patternParts as $i => $part) {
            if (preg_match('/^\{(\w+)\}$/', $part, $matches)) {
                $params[$matches[1]] = $uriParts[$i];
            } elseif ($part !== $uriParts[$i]) {
                return false;
            }
        }

        return $params;
    }

    public function resolve(Request $request, string $defaultStack = 'api'): array
    {
        $result = $this->match($request->method(), $request->uri());

        if ($result === null) {
            return ['controller' => null, 'method' => null, 'params' => [], 'middleware' => [], 'stack' => $defaultStack];
        }

        $handler = $result['handler'];
        $params = $result['params'];
        $stack = $defaultStack;
        $middleware = $result['middleware'];

        $handlerConfig = null;

        if (is_array($handler)) {
            if (isset($handler['handler'])) {
                $handlerConfig = $handler['handler'];
                if (isset($handler['stack'])) {
                    $stack = $handler['stack'];
                }
                $routeMiddleware = $handler['middleware'] ?? [];
                $middleware = array_merge($middleware, $routeMiddleware);
            } else {
                $handlerConfig = $handler[0];
            }
        } else {
            $handlerConfig = $handler;
        }

        if (is_array($handlerConfig)) {
            $handlerConfig = $handlerConfig[0];
        }

        $parts = explode('@', (string) $handlerConfig);
        $controller = $parts[0];
        $method = $parts[1] ?? 'index';

        return [
            'controller' => $controller,
            'method' => $method,
            'params' => $params,
            'middleware' => $middleware,
            'stack' => $stack,
        ];
    }
}