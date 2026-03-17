<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\DatabaseManager;
use Mall\Core\Logger;

class NotificationService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly WechatService $wechat,
        private readonly SettingsService $settings,
        private readonly Logger $logger
    ) {
    }

    public function notifyAdmins(string $eventKey, array $order): void
    {
        $config = $this->settings->getGroup('notifications');
        if (($config['admin_' . $eventKey . '_enabled'] ?? '0') !== '1') {
            return;
        }

        $templateId = (string) ($config['admin_' . $eventKey . '_template_id'] ?? '');
        if ($templateId === '') {
            return;
        }

        $stmt = $this->db->mall()->query(
            "SELECT openid FROM mall_users WHERE role = 'admin' AND openid IS NOT NULL AND openid <> '' AND status = 'active'"
        );

        foreach ($stmt->fetchAll() as $admin) {
            $this->wechat->sendTemplateMessage(
                (string) $admin['openid'],
                $templateId,
                $this->buildTemplateData($eventKey, $order),
                '/mall/admin'
            );
        }
    }

    public function notifyUser(string $eventKey, array $order, array $user): void
    {
        $config = $this->settings->getGroup('notifications');
        if (($config['user_' . $eventKey . '_enabled'] ?? '0') !== '1') {
            return;
        }

        $templateId = (string) ($config['user_' . $eventKey . '_template_id'] ?? '');
        if ($templateId === '' || empty($user['openid'])) {
            return;
        }

        $result = $this->wechat->sendTemplateMessage(
            (string) $user['openid'],
            $templateId,
            $this->buildTemplateData($eventKey, $order),
            '/mall/profile'
        );

        if (!($result['success'] ?? false)) {
            $this->logger->warning('notification', '微信模板消息发送失败', [
                'event' => $eventKey,
                'order_no' => $order['order_no'] ?? '',
                'response' => $result,
            ]);
        }
    }

    private function buildTemplateData(string $eventKey, array $order): array
    {
        $statusText = match ($eventKey) {
            'paid' => '付款成功',
            'created' => '订单已创建',
            'shipped' => '订单已发货',
            'completed' => '订单已完成',
            'closed' => '订单已关闭',
            'cancelled' => '订单已取消',
            default => '订单状态更新',
        };

        return [
            'first' => ['value' => '奇妙集市订单通知', 'color' => '#8d5a2b'],
            'keyword1' => ['value' => (string) ($order['order_no'] ?? '')],
            'keyword2' => ['value' => $statusText],
            'keyword3' => ['value' => '¥' . money_format_cn($order['payable_amount'] ?? 0)],
            'remark' => ['value' => '可进入奇妙集市用户中心或后台查看订单详情。'],
        ];
    }
}