<?php

declare(strict_types=1);

namespace Mall\Controllers;

use Mall\Core\App;
use Mall\Core\Request;
use Mall\Core\Response;

abstract class BaseController
{
    public function __construct(protected readonly App $app)
    {
    }

    protected function view(string $template, array $data = [], ?string $layout = 'layouts/main'): Response
    {
        $view = $this->app->make('view');
        $session = $this->app->make('session');
        $csrf = $this->app->make('csrf');

        $shared = [
            'appName' => $this->app->config()->get('app.name'),
            'flashSuccess' => $session->pullFlash('success'),
            'flashError' => $session->pullFlash('error'),
            'csrfToken' => $csrf->token(),
        ];

        return Response::html($view->render($template, array_merge($shared, $data), $layout));
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    protected function validateCsrf(Request $request): void
    {
        $csrf = $this->app->make('csrf');
        $token = (string) ($request->input('_csrf_token') ?? $request->header('X-CSRF-TOKEN', ''));

        if (!$csrf->validate($token)) {
            throw new \RuntimeException('CSRF 校验失败，请刷新页面后重试。');
        }
    }
}