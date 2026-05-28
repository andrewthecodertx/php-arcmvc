<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

class ServeStopCommand extends Command
{
    public function name(): string
    {
        return 'serve:stop';
    }

    public function description(): string
    {
        return 'Stop a detached development server';
    }

    public function run(array $args): int
    {
        $port = $this->option($args, 'port', '8080');
        $pidFile = sys_get_temp_dir() . '/arc-serve-' . $port . '.pid';

        if (!file_exists($pidFile)) {
            $this->error("No detached server found on port {$port}.");
            $this->line('  Make sure the server was started with --detach.');
            return 1;
        }

        $pid = (int) trim(file_get_contents($pidFile));

        if (!posix_kill($pid, 0)) {
            $this->warning("Process {$pid} is not running. Cleaning up stale PID file.");
            unlink($pidFile);
            return 0;
        }

        posix_kill($pid, SIGTERM);

        $waited = 0;
        while ($waited < 20) {
            if (!posix_kill($pid, 0)) {
                break;
            }
            usleep(100000);
            $waited++;
        }

        if (posix_kill($pid, 0)) {
            posix_kill($pid, SIGKILL);
        }

        unlink($pidFile);
        $this->success("Server on port {$port} stopped (PID {$pid}).");

        return 0;
    }
}