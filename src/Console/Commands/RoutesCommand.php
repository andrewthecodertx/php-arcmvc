<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

use Arc\Routing\Router;

class RoutesCommand extends Command
{
    public function name(): string
    {
        return 'routes';
    }

    public function description(): string
    {
        return 'List registered routes';
    }

    public function run(array $args): int
    {
        $routesFile = getcwd() . '/routes/web.php';

        if (!file_exists($routesFile)) {
            $this->warning('No routes/web.php found. Create it to register routes.');
            return 1;
        }

        $router = new Router();
        require $routesFile;

        $routes = $router->getRoutes();

        if (empty($routes)) {
            $this->warning('No routes registered.');
            return 0;
        }

        $this->info('Registered Routes:');
        $this->line('');

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $callback = $route['callback'];
                if (is_array($callback)) {
                    $target = $callback[0] . '@' . $callback[1];
                } else {
                    $target = 'Closure';
                }
                $this->line(sprintf(
                    '  %-7s %-30s %s',
                    $method,
                    $route['path'],
                    $target,
                ));
            }
        }

        return 0;
    }
}