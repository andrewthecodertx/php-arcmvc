<?php

declare(strict_types=1);

namespace Arc\Routing;

use Exception;

class RouteNotFoundException extends Exception
{
    public function __construct(string $path)
    {
        parent::__construct("Route not found: {$path}", 404);
    }
}