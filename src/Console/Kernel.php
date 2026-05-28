<?php

declare(strict_types=1);

namespace Arc\Console;

use Arc\Console\Commands\Command;
use Arc\Console\Commands\HelpCommand;
use Arc\Console\Commands\MakeControllerCommand;
use Arc\Console\Commands\MakeModelCommand;
use Arc\Console\Commands\ServeCommand;
use Arc\Console\Commands\ServeStopCommand;
use Arc\Console\Commands\RoutesCommand;

class Kernel
{
    private array $commands = [];

    public function __construct()
    {
        $this->registerDefaultCommands();
    }

    public function register(Command $command): self
    {
        $this->commands[$command->name()] = $command;
        return $this;
    }

    public function handle(array $argv): int
    {
        array_shift($argv);

        $name = $argv[0] ?? 'help';

        if ($name === 'help' || $name === '--help' || $name === '-h') {
            $this->commands['help']->run([]);
            return 0;
        }

        if (!isset($this->commands[$name])) {
            echo "Unknown command: {$name}\n";
            echo "Run 'arc help' to see available commands.\n";
            return 1;
        }

        $args = array_slice($argv, 1);
        return $this->commands[$name]->run($args);
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    private function registerDefaultCommands(): void
    {
        $this->register(new HelpCommand($this));
        $this->register(new ServeCommand());
        $this->register(new ServeStopCommand());
        $this->register(new MakeControllerCommand());
        $this->register(new MakeModelCommand());
        $this->register(new RoutesCommand());
    }
}