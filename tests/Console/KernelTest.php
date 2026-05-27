<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Arc\Console\Kernel;

class KernelTest extends TestCase
{
    public function testHasDefaultCommands(): void
    {
        $kernel = new Kernel();
        $commands = $kernel->getCommands();

        $this->assertArrayHasKey('help', $commands);
        $this->assertArrayHasKey('serve', $commands);
        $this->assertArrayHasKey('make:controller', $commands);
        $this->assertArrayHasKey('make:model', $commands);
        $this->assertArrayHasKey('routes', $commands);
    }

    public function testHandleHelpReturnsZero(): void
    {
        $kernel = new Kernel();
        ob_start();
        $exit = $kernel->handle(['arc', 'help']);
        ob_end_clean();
        $this->assertSame(0, $exit);
    }

    public function testHandleUnknownCommandReturnsOne(): void
    {
        $kernel = new Kernel();
        ob_start();
        $exit = $kernel->handle(['arc', 'nonexistent']);
        ob_end_clean();
        $this->assertSame(1, $exit);
    }

    public function testRegisterCustomCommand(): void
    {
        $kernel = new Kernel();
        $command = new class extends \Arc\Console\Commands\Command {
            public function name(): string { return 'custom:test'; }
            public function description(): string { return 'Custom test command'; }
            public function run(array $args): int { return 0; }
        };

        $kernel->register($command);
        $this->assertArrayHasKey('custom:test', $kernel->getCommands());
    }

    public function testCommandBaseOptionParsing(): void
    {
        $command = new class extends \Arc\Console\Commands\Command {
            public function name(): string { return 'test'; }
            public function description(): string { return 'test'; }
            public function run(array $args): int { return 0; }
            public function testOption(array $args, string $name, mixed $default = null): mixed {
                return $this->option($args, $name, $default);
            }
            public function testHasOption(array $args, string $name): bool {
                return $this->hasOption($args, $name);
            }
        };

        $this->assertSame('8080', $command->testOption(['--port', '8080'], 'port'));
        $this->assertSame('8080', $command->testOption(['--port=8080'], 'port'));
        $this->assertNull($command->testOption([], 'port'));
        $this->assertSame(3000, $command->testOption([], 'port', 3000));

        $this->assertTrue($command->testHasOption(['--verbose'], 'verbose'));
        $this->assertFalse($command->testHasOption([], 'verbose'));
    }
}