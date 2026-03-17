<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }
}

if (!function_exists('load_env')) {
    function load_env(string $path): void
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('route_path')) {
    function route_path(string $path = ''): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('json_encode_unicode')) {
    function json_encode_unicode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('money_format_cn')) {
    function money_format_cn(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', trim($text));
        $slug = trim((string) $slug, '-');
        return strtolower($slug !== '' ? $slug : 'item-' . bin2hex(random_bytes(4)));
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $array;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
