<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

abstract class Command
{
    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function run(array $args): int;

    protected function info(string $message): void
    {
        echo "\033[36m{$message}\033[0m\n";
    }

    protected function success(string $message): void
    {
        echo "\033[32m{$message}\033[0m\n";
    }

    protected function warning(string $message): void
    {
        echo "\033[33m{$message}\033[0m\n";
    }

    protected function error(string $message): void
    {
        echo "\033[31m{$message}\033[0m\n";
    }

    protected function line(string $message): void
    {
        echo "{$message}\n";
    }

    protected function option(array $args, string $name, mixed $default = null): mixed
    {
        $flag = "--{$name}";
        foreach ($args as $i => $arg) {
            if ($arg === $flag && isset($args[$i + 1])) {
                return $args[$i + 1];
            }
            if (str_starts_with($arg, "{$flag}=")) {
                return substr($arg, strlen($flag) + 1);
            }
        }
        return $default;
    }

    protected function hasOption(array $args, string $name): bool
    {
        $flag = "--{$name}";
        foreach ($args as $arg) {
            if ($arg === $flag || str_starts_with($arg, "{$flag}=")) {
                return true;
            }
        }
        return false;
    }
}