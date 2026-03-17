<?php

declare(strict_types=1);

return [
    'min_level' => env('LOG_LEVEL', 'info'),
    'max_size_mb' => (int) env('LOG_MAX_SIZE_MB', '10'),
    'retention_days' => (int) env('LOG_RETENTION_DAYS', '30'),
];
