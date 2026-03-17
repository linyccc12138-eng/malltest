<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var \Mall\Services\OrderService $orders */
$orders = $app->make('orders');
$orders->closeExpiredOrders();

echo '[' . date('Y-m-d H:i:s') . "] expired orders checked\n";
