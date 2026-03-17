<?php

declare(strict_types=1);

namespace Mall\Core;

class Config
{
    private array $items = [];

    public function __construct(string $configPath)
    {
        $files = glob(rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_get($this->items, $key, $default);
    }

    public function all(): array
    {
        return $this->items;
    }
}
