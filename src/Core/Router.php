<?php

declare(strict_types=1);

namespace Mall\Core;

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add(['GET'], $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add(['POST'], $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): void
    {
        $this->add(['PUT'], $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): void
    {
        $this->add(['DELETE'], $pattern, $handler);
    }

    public function match(array $methods, string $pattern, callable|array $handler): void
    {
        $this->add($methods, $pattern, $handler);
    }

    private function add(array $methods, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request, App $app): Response
    {
        $requestMethod = $request->method === 'HEAD' ? 'GET' : $request->method;

        foreach ($this->routes as $route) {
            if (!in_array($requestMethod, $route['methods'], true)) {
                continue;
            }

            $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', '(?P<$1>[^/]+)', $route['pattern']);
            $regex = '#^' . $regex . '$#';

            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }

            $params = array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];
            $response = call_user_func($handler, $request, $params);
            if ($response instanceof Response) {
                return $response;
            }

            if (is_array($response)) {
                return Response::json($response);
            }

            return Response::html((string) $response);
        }

        return Response::html('<h1>404</h1><p>页面不存在。</p>', 404);
    }
}