<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

use Arc\Console\Kernel;

class HelpCommand extends Command
{
    public function __construct(private Kernel $kernel)
    {
    }

    public function name(): string
    {
        return 'help';
    }

    public function description(): string
    {
        return 'List available commands';
    }

    public function run(array $args): int
    {
        $this->info('Arc Framework');
        $this->line('');
        $this->line('Available commands:');

        $commands = $this->kernel->getCommands();
        $maxLen = max(array_map(fn (Command $c) => strlen($c->name()), $commands));

        foreach ($commands as $command) {
            $pad = str_repeat(' ', $maxLen - strlen($command->name()) + 2);
            $this->line("  {$command->name()}{$pad}{$command->description()}");
        }

        return 0;
    }
}