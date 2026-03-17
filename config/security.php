<?php

declare(strict_types=1);

return [
    'app_key' => env('APP_KEY', 'change-this-key-to-a-32-byte-secret-for-production'),
    'csrf_token_name' => '_csrf_token',
    'login_rate_limit' => 5,
    'login_rate_limit_window' => 60,
];
