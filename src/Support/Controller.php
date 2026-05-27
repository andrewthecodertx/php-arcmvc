<?php

declare(strict_types=1);

namespace Arc\Support;

use Arc\Http\Request;
use Arc\Http\Response;

abstract class Controller
{
    protected Request $request;

    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    protected function view(string $template, array $data = []): Response
    {
        $view = new \Arc\View\Renderer();
        return $view->render($template, $data);
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