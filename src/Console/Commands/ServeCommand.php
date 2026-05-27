<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

class ServeCommand extends Command
{
    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Start the built-in PHP development server';
    }

    public function run(array $args): int
    {
        $host = $this->option($args, 'host', '127.0.0.1');
        $port = $this->option($args, 'port', '8080');
        $docroot = $this->option($args, 'docroot', 'public');

        if (!is_dir($docroot)) {
            $this->error("Document root not found: {$docroot}");
            return 1;
        }

        $this->info("Arc development server starting on http://{$host}:{$port}");
        $this->line("Document root: {$docroot}");
        $this->line("Press Ctrl+C to stop");
        $this->line('');

        passthru("php -S {$host}:{$port} -t {$docroot}");

        return 0;
    }
}