<?php

declare(strict_types=1);

namespace Arc\Http;

class SapiEmitter
{
    public function emit(Response $response): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $response->getContent();
    }
}
