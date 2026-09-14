<?php

declare(strict_types=1);

namespace App\Core;

class Bootstrap
{
    private Container $container;
    private Router $router;

    public function __construct()
    {
        $this->container = new Container();
        $this->router = new Router();
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function registerRoutes(): void
    {
        $routeDefinitions = require dirname(__DIR__, 2) . '/config/routes.php';

        foreach ($routeDefinitions as $key => $config) {
            [$method, $path] = array_pad(explode(' ', $key, 2), 2, '');

            if (is_array($config)) {
                $handler = $config['handler'] ?? null;
                $middleware = $config['middleware'] ?? [];

                if ($handler !== null) {
                    $this->router->add($method, $path, [
                        'handler' => $handler,
                        'stack' => $config['stack'] ?? 'api',
                        'middleware' => $middleware,
                    ]);
                }
            } else {
                $this->router->add($method, $path, $config);
            }
        }
    }

    public function run(Request $request): void
    {
        $start = microtime(true);
        $resolved = $this->router->resolve($request);

        $request->setRouteParams($resolved['params']);

        if ($resolved['controller'] === null) {
            Response::notFound('Endpoint not found');
            $this->logApiRequest($request, 404, $start);
            return;
        }

        $controllerName = 'App\\Controllers\\' . $resolved['controller'];
        $methodName = $resolved['method'];

        $pipeline = $this->buildPipeline($resolved);
        $pipeline->run($request, function (Request $req) use ($controllerName, $methodName): void {
            if (!class_exists($controllerName)) {
                Response::notFound('Endpoint not found');
                return;
            }

            $controller = new $controllerName();

            if (!method_exists($controller, $methodName)) {
                Response::notFound('Endpoint not found');
                return;
            }

            $controller->$methodName($req);
        });

        $this->logApiRequest($request, http_response_code(), $start);
    }

    private function logApiRequest(Request $request, int $status, float $start): void
    {
        $duration = (int) ((microtime(true) - $start) * 1000);
        $userId = $request->getUserId();

        $entry = [
            'timestamp' => gmdate('c'),
            'request_id' => $request->getRequestId(),
            'method' => $request->method(),
            'endpoint' => $request->uri(),
            'status' => $status,
            'duration_ms' => $duration,
            'user_id' => $userId,
            'ip' => $request->ip(),
        ];

        $logPath = dirname(__DIR__, 2) . '/storage/logs/api.log';
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logPath,
            json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function buildPipeline(array $resolved): MiddlewarePipeline
    {
        $stacks = require dirname(__DIR__, 2) . '/config/middleware.php';
        $stackName = $resolved['stack'] ?? 'api';
        $stackClasses = $stacks['stacks'][$stackName] ?? [];

        $perRoute = $resolved['middleware'] ?? [];
        $routeClasses = [];
        foreach ($perRoute as $entry) {
            if (str_starts_with($entry, 'rate_limit:')) {
                continue;
            }
            $className = explode(':', $entry, 2)[0];
            if (class_exists($className)) {
                $routeClasses[] = $entry;
            }
        }

        $pipeline = new MiddlewarePipeline();
        $pipeline->addMultiple($stackClasses);
        $pipeline->addMultiple($routeClasses);

        return $pipeline;
    }
}