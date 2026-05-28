<?php

declare(strict_types=1);

namespace Arc\Container;

use Psr\Container\ContainerInterface;
use RuntimeException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $singletons = [];
    private array $resolved = [];

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function get(string $id): mixed
    {
        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (isset($this->singletons[$id])) {
            $concrete = $this->singletons[$id];
            $instance = is_callable($concrete) ? $concrete($this) : new $concrete();
            $this->resolved[$id] = $instance;
            return $instance;
        }

        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id];
            return is_callable($concrete) ? $concrete($this) : new $concrete();
        }

        if (class_exists($id)) {
            return new $id();
        }

        throw new RuntimeException("No binding found for: {$id}");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->singletons[$id]) || class_exists($id);
    }
}
