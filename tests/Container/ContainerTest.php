<?php

declare(strict_types=1);

namespace Tests\Container;

use PHPUnit\Framework\TestCase;
use Arc\Container\Container;
use RuntimeException;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // --- Basic binding and resolution ---

    public function testBindAndResolveCallable(): void
    {
        $this->container->bind('greeting', fn () => 'hello');
        $this->assertSame('hello', $this->container->get('greeting'));
    }

    public function testBindAndResolveClassName(): void
    {
        $this->container->bind(SimpleService::class, SimpleService::class);
        $service = $this->container->get(SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $service);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton('counter', fn () => new \stdClass());
        $a = $this->container->get('counter');
        $b = $this->container->get('counter');
        $this->assertSame($a, $b);
    }

    public function testBindReturnsNewInstanceEachTime(): void
    {
        $this->container->bind('counter', fn () => new \stdClass());
        $a = $this->container->get('counter');
        $b = $this->container->get('counter');
        $this->assertNotSame($a, $b);
    }

    public function testHasReturnsTrueForBinding(): void
    {
        $this->container->bind('key', fn () => 'value');
        $this->assertTrue($this->container->has('key'));
    }

    public function testHasReturnsTrueForExistingClass(): void
    {
        $this->assertTrue($this->container->has(SimpleService::class));
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $this->assertFalse($this->container->has('nonexistent_key'));
    }

    public function testGetThrowsForUnknown(): void
    {
        $this->expectException(RuntimeException::class);
        $this->container->get('nonexistent_key');
    }

    // --- Auto-wiring ---

    public function testAutoWiringResolvesNoArgConstructor(): void
    {
        $service = $this->container->get(SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $service);
    }

    public function testAutoWiringResolvesTypedDependencies(): void
    {
        $service = $this->container->get(ServiceWithDependency::class);
        $this->assertInstanceOf(ServiceWithDependency::class, $service);
        $this->assertInstanceOf(SimpleService::class, $service->inner);
    }

    public function testAutoWiringResolvesNestedDependencies(): void
    {
        $service = $this->container->get(TopLevelService::class);
        $this->assertInstanceOf(TopLevelService::class, $service);
        $this->assertInstanceOf(ServiceWithDependency::class, $service->middle);
        $this->assertInstanceOf(SimpleService::class, $service->middle->inner);
    }

    public function testAutoWiringUsesDefaultValues(): void
    {
        $service = $this->container->get(ServiceWithDefault::class);
        $this->assertInstanceOf(ServiceWithDefault::class, $service);
        $this->assertSame('default', $service->name);
    }

    public function testAutoWiringUsesBoundDependencies(): void
    {
        $instance = new SimpleService();
        $this->container->bind(SimpleService::class, fn () => $instance);

        $service = $this->container->get(ServiceWithDependency::class);
        $this->assertSame($instance, $service->inner);
    }

    public function testAutoWiringThrowsForUnresolvableScalarParam(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve');
        $this->container->get(ServiceWithScalarParam::class);
    }

    // --- Helper classes for testing ---

    public function testSingletonWithStringConcrete(): void
    {
        $this->container->singleton(SimpleService::class, SimpleService::class);
        $a = $this->container->get(SimpleService::class);
        $b = $this->container->get(SimpleService::class);
        $this->assertSame($a, $b);
    }

    public function testBindWithStringConcreteAutoWires(): void
    {
        $this->container->bind(ServiceWithDependency::class, ServiceWithDependency::class);
        $service = $this->container->get(ServiceWithDependency::class);
        $this->assertInstanceOf(ServiceWithDependency::class, $service);
        $this->assertInstanceOf(SimpleService::class, $service->inner);
    }
}

// --- Test fixtures ---

class SimpleService
{
}

class ServiceWithDependency
{
    public SimpleService $inner;

    public function __construct(SimpleService $inner)
    {
        $this->inner = $inner;
    }
}

class TopLevelService
{
    public ServiceWithDependency $middle;

    public function __construct(ServiceWithDependency $middle)
    {
        $this->middle = $middle;
    }
}

class ServiceWithDefault
{
    public string $name;

    public function __construct(string $name = 'default')
    {
        $this->name = $name;
    }
}

class ServiceWithScalarParam
{
    public function __construct(string $name)
    {
    }
}