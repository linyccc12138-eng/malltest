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
        $normalizedPath = ltrim($path, '/');
        $assetPath = '/assets/' . $normalizedPath;
        $fullPath = base_path('public/assets/' . $normalizedPath);

        if (is_file($fullPath)) {
            return $assetPath . '?v=' . filemtime($fullPath);
        }

        return $assetPath;
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

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $value): string|false
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($normalized, true);
    }
}

if (!function_exists('order_access_token_secret')) {
    function order_access_token_secret(): string
    {
        $secret = (string) env('APP_KEY', '');
        return $secret !== '' ? $secret : 'magic-mall-order-access';
    }
}

if (!function_exists('generate_order_access_token')) {
    function generate_order_access_token(int $orderId, string $orderNo): string
    {
        $normalizedOrderNo = trim($orderNo);
        if ($orderId <= 0 || $normalizedOrderNo === '') {
            return '';
        }

        $payload = base64url_encode(json_encode_unicode([
            'id' => $orderId,
            'order_no' => $normalizedOrderNo,
        ]));
        $signature = base64url_encode(hash_hmac('sha256', $payload, order_access_token_secret(), true));

        return $payload . '.' . $signature;
    }
}

if (!function_exists('parse_order_access_token')) {
    function parse_order_access_token(string $token): ?array
    {
        $normalizedToken = trim($token);
        if ($normalizedToken === '' || !str_contains($normalizedToken, '.')) {
            return null;
        }

        [$payloadPart, $signaturePart] = explode('.', $normalizedToken, 2);
        if ($payloadPart === '' || $signaturePart === '') {
            return null;
        }

        $expectedSignature = base64url_encode(hash_hmac('sha256', $payloadPart, order_access_token_secret(), true));
        if (!hash_equals($expectedSignature, $signaturePart)) {
            return null;
        }

        $decodedPayload = base64url_decode($payloadPart);
        if ($decodedPayload === false) {
            return null;
        }

        $payload = json_decode($decodedPayload, true);
        if (!is_array($payload)) {
            return null;
        }

        $orderId = (int) ($payload['id'] ?? 0);
        $orderNo = trim((string) ($payload['order_no'] ?? ''));
        if ($orderId <= 0 || $orderNo === '') {
            return null;
        }

        return [
            'id' => $orderId,
            'order_no' => $orderNo,
        ];
    }
}
