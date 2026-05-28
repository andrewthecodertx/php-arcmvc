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
        return 'Start the development server';
    }

    public function run(array $args): int
    {
        $host = $this->option($args, 'host', '127.0.0.1');
        $port = $this->option($args, 'port', '8080');
        $docroot = $this->option($args, 'docroot', $this->detectDocroot());
        $detach = $this->hasOption($args, 'detach');

        if (!is_dir($docroot)) {
            $this->error("Document root not found: {$docroot}");
            $this->line('');
            $this->line('Make sure you are in an Arc project directory, or specify --docroot.');
            return 1;
        }

        $routerScript = $this->resolveRouterScript($docroot);

        if ($routerScript === null) {
            $this->error("Router script not found. Expected arc-router.php in {$docroot}");
            return 1;
        }

        if ($detach) {
            return $this->runDetached($host, $port, $docroot, $routerScript);
        }

        $this->printBanner($host, $port, $docroot);

        $command = $this->buildServerCommand($host, $port, $docroot, $routerScript);

        passthru($command, $exitCode);

        return $exitCode;
    }

    private function runDetached(string $host, string $port, string $docroot, string $routerScript): int
    {
        $pidFile = $this->pidFile($port);

        if (file_exists($pidFile)) {
            $oldPid = (int) file_get_contents($pidFile);
            if (posix_kill($oldPid, 0)) {
                $this->error("Server already running on port {$port} (PID {$oldPid})");
                $this->line("  Run 'arc serve:stop --port={$port}' to stop it first.");
                return 1;
            }
            unlink($pidFile);
        }

        $command = $this->buildServerCommand($host, $port, $docroot, $routerScript);

        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            $this->error('Failed to start server process.');
            return 1;
        }

        $pid = proc_get_status($process)['pid'];
        file_put_contents($pidFile, (string) $pid);

        usleep(200000);

        if (!@fsockopen($host, (int) $port, $errno, $errstr, 1)) {
            $this->error("Server failed to start on port {$port}.");
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            return 1;
        }

        $url = "http://{$host}:{$port}";
        $this->success("Arc dev server started in background");
        $this->line("  PID:   {$pid}");
        $this->line("  URL:   {$url}");
        $this->line("  Stop:  arc serve:stop --port={$port}");

        proc_close($process);

        return 0;
    }

    private function detectDocroot(): string
    {
        $cwd = getcwd();

        if (is_dir($cwd . '/public')) {
            return $cwd . '/public';
        }

        return $cwd;
    }

    private function resolveRouterScript(string $docroot): ?string
    {
        $projectRouter = $docroot . '/arc-router.php';

        if (file_exists($projectRouter)) {
            return $projectRouter;
        }

        $bundledRouter = dirname(__DIR__, 2) . '/Console/arc-router.php';

        if (file_exists($bundledRouter)) {
            return $bundledRouter;
        }

        return null;
    }

    private function buildServerCommand(string $host, string $port, string $docroot, string $routerScript): string
    {
        return sprintf(
            'php -S %s:%s -t %s %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docroot),
            escapeshellarg($routerScript),
        );
    }

    private function pidFile(string $port): string
    {
        return sys_get_temp_dir() . '/arc-serve-' . $port . '.pid';
    }

    private function printBanner(string $host, string $port, string $docroot): void
    {
        $url = "http://{$host}:{$port}";

        $this->line('');
        $this->info('  ___  _____________________');
        $this->info(' / _ |/ ___/ ___/ ___/ ___/');
        $this->info('/ __ |/ /  / /__/ /__/ /__');
        $this->info('_/ |_\\___/\\___/\\___/\\___/');
        $this->line('');
        $this->success("  Arc dev server started");
        $this->line('');
        $this->line("  URL:      {$url}");
        $this->line("  Docroot:  {$docroot}");
        $this->line("  PHP:      " . PHP_VERSION);
        $this->line('');
        $this->warning('  Press Ctrl+C to stop');
        $this->line('');
    }
}