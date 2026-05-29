<?php

declare(strict_types=1);

namespace Arc\Container;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
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

    /**
     * @template T
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): mixed
    {
        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (isset($this->singletons[$id])) {
            $concrete = $this->singletons[$id];
            $instance = is_callable($concrete) ? $concrete($this) : $this->resolve($concrete);
            $this->resolved[$id] = $instance;
            return $instance;
        }

        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id];
            return is_callable($concrete) ? $concrete($this) : $this->resolve($concrete);
        }

        if (class_exists($id)) {
            return $this->resolve($id);
        }

        throw new RuntimeException("No binding found for: {$id}");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->singletons[$id]) || class_exists($id);
    }

    /**
     * Resolve a class using reflection-based auto-wiring.
     * Constructor parameters are resolved from the container when possible.
     * Parameters with no type or scalar types require explicit binding.
     */
    private function resolve(string $class): object
    {
        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Class {$class} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new RuntimeException(
                    "Unable to resolve parameter \${$parameter->getName()} in {$class}::__construct()"
                );
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}