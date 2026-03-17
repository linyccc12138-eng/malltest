<?php

declare(strict_types=1);

namespace Mall\Core;

class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $server,
        public readonly array $files,
        public readonly string $rawBody
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawBody = file_get_contents('php://input') ?: '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $body = $_POST;

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        return new self(
            $method,
            $path,
            $_GET,
            $body,
            $_SERVER,
            $_FILES,
            $rawBody
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$headerName] ?? $default;
    }

    public function expectsJson(): bool
    {
        return str_contains((string) $this->header('Accept', ''), 'application/json')
            || str_contains((string) ($this->server['CONTENT_TYPE'] ?? ''), 'application/json')
            || str_starts_with($this->path, '/mall/api/');
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? 'unknown');
    }
}
