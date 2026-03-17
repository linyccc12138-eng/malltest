<?php

declare(strict_types=1);

return [
    'mall' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'magic_mall'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'membership' => [
        'host' => env('MEMBER_DB_HOST', '127.0.0.1'),
        'port' => env('MEMBER_DB_PORT', '3306'),
        'database' => env('MEMBER_DB_DATABASE', 'membership_center'),
        'username' => env('MEMBER_DB_USERNAME', 'root'),
        'password' => env('MEMBER_DB_PASSWORD', ''),
        'charset' => env('MEMBER_DB_CHARSET', 'utf8mb4'),
    ],
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', '6379'),
        'password' => env('REDIS_PASSWORD', ''),
        'database' => (int) env('REDIS_DB', '0'),
        'timeout' => (float) env('REDIS_TIMEOUT', '1.5'),
    ],
];
