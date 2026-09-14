<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;

class MiddlewarePipeline
{
    private array $middlewareClasses = [];

    private static array $instances = [];

    public function add(string $middlewareClass): void
    {
        $this->middlewareClasses[] = $middlewareClass;
    }

    public function addMultiple(array $classes): void
    {
        foreach ($classes as $class) {
            $this->add($class);
        }
    }

    public function run(Request $request, callable $handler): void
    {
        $pipeline = array_reverse($this->middlewareClasses);

        $core = function (Request $req) use ($handler): void {
            $handler($req);
        };

        $lastCallable = $core;

        foreach ($pipeline as $className) {
            $middleware = $this->resolve($className);
            $nextCallable = $lastCallable;
            $lastCallable = function (Request $req) use ($middleware, $nextCallable): void {
                $middleware->handle($req, $nextCallable);
                if (Response::isSent()) {
                    return;
                }
            };
        }

        $lastCallable($request);
    }

    private function resolve(string $entry): MiddlewareInterface
    {
        if (!isset(self::$instances[$entry])) {
            [$className, $param] = array_pad(explode(':', $entry, 2), 2, null);
            self::$instances[$entry] = $param !== null ? new $className($param) : new $className();
        }

        return self::$instances[$entry];
    }
}