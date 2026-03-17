<?php

declare(strict_types=1);

namespace Mall\Core;

class Response
{
    public function __construct(
        private string $content,
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        return new self($content, $status, $headers);
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self(json_encode_unicode($data), $status, $headers);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }
}
