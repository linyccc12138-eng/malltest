<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', '奇妙集市'),
    'env' => env('APP_ENV', 'development'),
    'debug' => env('APP_DEBUG', 'false') === 'true',
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),
    'session_name' => env('SESSION_NAME', 'magic_mall'),
    'session_path' => BASE_PATH . '/storage/sessions',
    'upload_path' => BASE_PATH . '/storage/uploads',
    'base_path' => BASE_PATH,
];