<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\DatabaseManager;

class DashboardService
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function overview(): array
    {
        $pdo = $this->db->mall();

        $todayOrders = (int) (($pdo->query("SELECT COUNT(*) AS total FROM orders WHERE DATE(created_at) = CURRENT_DATE")?->fetch()['total']) ?? 0);
        $todayGmv = (float) (($pdo->query("SELECT COALESCE(SUM(paid_amount), 0) AS total FROM orders WHERE DATE(paid_at) = CURRENT_DATE AND payment_status = 'paid'")?->fetch()['total']) ?? 0);
        $todayNewUsers = (int) (($pdo->query("SELECT COUNT(*) AS total FROM mall_users WHERE DATE(created_at) = CURRENT_DATE")?->fetch()['total']) ?? 0);
        $stockAlerts = (int) (($pdo->query("SELECT COUNT(*) AS total FROM products WHERE stock_total <= 5")?->fetch()['total']) ?? 0);

        $salesStmt = $pdo->query(
            "SELECT DATE(COALESCE(paid_at, created_at)) AS sale_date, SUM(paid_amount) AS amount
             FROM orders
             WHERE payment_status = 'paid' AND COALESCE(paid_at, created_at) >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY)
             GROUP BY DATE(COALESCE(paid_at, created_at))
             ORDER BY sale_date ASC"
        );

        return [
            'today_orders' => $todayOrders,
            'today_gmv' => $todayGmv,
            'today_new_users' => $todayNewUsers,
            'stock_alerts' => $stockAlerts,
            'recent_sales' => $salesStmt->fetchAll(),
        ];
    }
}
