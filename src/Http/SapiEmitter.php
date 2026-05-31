<?php

declare(strict_types=1);

namespace Arc\Http;

class SapiEmitter
{
    public function emit(Response $response): void
    {
        if (headers_sent($file, $line)) {
            throw new \RuntimeException("Headers already sent in {$file}:{$line}");
        }

        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }

        // Cookies are emitted as repeated Set-Cookie headers (replace = false)
        // so multiple cookies on one response are all sent to the client.
        foreach ($response->getCookies() as $cookie) {
            header('Set-Cookie: ' . $cookie->toHeader(), false);
        }

        echo $response->getContent();
    }
}
