<?php

declare(strict_types=1);

namespace App\Core;

class Container
{
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];

    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function make(string $abstract)
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (array_key_exists($abstract, $this->singletons)) {
            $this->instances[$abstract] = ($this->singletons[$abstract])($this);
            return $this->instances[$abstract];
        }

        if (array_key_exists($abstract, $this->bindings)) {
            return ($this->bindings[$abstract])($this);
        }

        if (class_exists($abstract)) {
            return new $abstract();
        }

        return null;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->singletons[$abstract])
            || isset($this->instances[$abstract])
            || class_exists($abstract);
    }
}
