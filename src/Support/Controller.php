<?php

declare(strict_types=1);

namespace Arc\Support;

use Arc\Application;
use Arc\Http\Request;
use Arc\Http\Response;

abstract class Controller
{
    protected Request $request;
    private ?Application $app = null;

    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    public function setApp(Application $app): self
    {
        $this->app = $app;
        return $this;
    }

    protected function view(string $template, array $data = []): Response
    {
        $renderer = $this->app
            ? $this->app->make(\Arc\View\Renderer::class)
            : new \Arc\View\Renderer();

        return $renderer->render($template, $data);
    }

    protected function json(array $data, int $status = 200): Response
    {
        $response = new Response();
        $response->json($data, $status);
        return $response;
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        $response = new Response();
        $response->redirect($url, $status);
        return $response;
    }

    protected function back(): Response
    {
        $referer = $this->request->getHeader('Referer') ?? '/';
        return $this->redirect($referer);
    }
}