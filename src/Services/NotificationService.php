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

    public function notifyAdmins(string $eventKey, array $order, ?array $user = null): void
    {
        $notificationKey = match ($eventKey) {
            'paid' => 'admin_paid',
            'cancelled' => 'admin_cancelled',
            default => '',
        };
        if ($notificationKey === '') {
            return;
        }

        $config = $this->settings->getGroup('notifications');
        if (($config[$notificationKey . '_enabled'] ?? '0') !== '1') {
            return;
        }

        $templateId = (string) ($config[$notificationKey . '_template_id'] ?? '');
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
                $this->buildTemplateData($notificationKey, $order, $user),
                $this->buildOrderDetailPath($order)
            );
        }
    }

    public function notifyUser(string $eventKey, array $order, array $user): void
    {
        $notificationKey = match ($eventKey) {
            'paid' => 'user_paid',
            'shipped' => 'user_shipped',
            'cancelled' => 'user_cancelled',
            default => '',
        };
        if ($notificationKey === '') {
            return;
        }

        $config = $this->settings->getGroup('notifications');
        if (($config[$notificationKey . '_enabled'] ?? '0') !== '1') {
            return;
        }

        $templateId = (string) ($config[$notificationKey . '_template_id'] ?? '');
        if ($templateId === '' || empty($user['openid'])) {
            return;
        }

        $result = $this->wechat->sendTemplateMessage(
            (string) $user['openid'],
            $templateId,
            $this->buildTemplateData($notificationKey, $order, $user),
            $this->buildOrderDetailPath($order)
        );

        if (!($result['success'] ?? false)) {
            $this->logger->warning('notification', '微信模板消息发送失败', [
                'event' => $eventKey,
                'order_no' => $order['order_no'] ?? '',
                'response' => $result,
            ]);
        }
    }

    private function buildTemplateData(string $notificationKey, array $order, ?array $user = null): array
    {
        return match ($notificationKey) {
            'admin_paid', 'user_paid' => $this->buildPaidTemplateData($order, $user),
            'admin_cancelled', 'user_cancelled' => $this->buildCancelledTemplateData($order),
            'user_shipped' => $this->buildShippedTemplateData($order),
            default => [],
        };
    }

    private function buildPaidTemplateData(array $order, ?array $user = null): array
    {
        $resolvedUser = $user ?? $this->findUserByOrder($order);
        $nickname = trim((string) ($resolvedUser['nickname'] ?? $resolvedUser['phone'] ?? $resolvedUser['username'] ?? ''));
        $receiverName = trim((string) (($order['address_snapshot']['receiver_name'] ?? $order['receiver_name'] ?? '')));
        $customerName = $this->composeDisplayText([$nickname, $receiverName], '/');
        $summary = $this->firstGoodsSummary($order['items'] ?? []);

        return [
            'thing5' => ['value' => $this->truncateThingField($customerName === '' ? '客户' : $customerName)],
            'thing6' => ['value' => $this->truncateThingField($summary === '' ? '商城商品' : $summary)],
            'character_string9' => ['value' => (string) $this->orderItemCount($order['items'] ?? [])],
            'amount7' => ['value' => '¥' . money_format_cn($order['payable_amount'] ?? 0)],
        ];
    }

    private function buildCancelledTemplateData(array $order): array
    {
        return [
            'character_string1' => ['value' => (string) ($order['order_no'] ?? '')],
            'const2' => ['value' => $this->cancelReasonText((string) ($order['closed_reason'] ?? ''))],
            'time3' => ['value' => $this->formatTemplateTime((string) ($order['closed_at'] ?? now()))],
        ];
    }

    private function buildShippedTemplateData(array $order): array
    {
        $address = $this->composeAddress($order);

        return [
            'character_string2' => ['value' => (string) ($order['order_no'] ?? '')],
            'time3' => ['value' => $this->formatTemplateTime((string) ($order['shipped_at'] ?? now()))],
            'amount4' => ['value' => '¥' . money_format_cn($order['payable_amount'] ?? 0)],
            'thing8' => ['value' => $this->truncateThingField($address === '' ? '地址未填写' : $address)],
            'phone_number7' => ['value' => (string) (($order['address_snapshot']['receiver_phone'] ?? $order['receiver_phone'] ?? ''))],
        ];
    }

    private function findUserByOrder(array $order): ?array
    {
        $userId = (int) ($order['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->mall()->prepare('SELECT id, username, nickname, phone FROM mall_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    private function firstGoodsSummary(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $firstName = trim((string) ($items[0]['product_name'] ?? ''));
        if ($firstName === '') {
            return '';
        }

        return count($items) > 1 ? $firstName . '等' : $firstName;
    }

    private function orderItemCount(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $total += (int) ($item['quantity'] ?? 0);
        }

        return max(1, $total);
    }

    private function composeAddress(array $order): string
    {
        $address = $order['address_snapshot'] ?? [];
        if (!is_array($address)) {
            $address = [];
        }

        return $this->composeDisplayText([
            (string) ($address['province'] ?? ''),
            (string) ($address['city'] ?? ''),
            (string) ($address['district'] ?? ''),
            (string) ($address['detail_address'] ?? ''),
        ]);
    }

    private function cancelReasonText(string $closedReason): string
    {
        return match ($closedReason) {
            'user_cancelled' => '用户主动取消',
            'admin_closed' => '管理员取消订单',
            default => str_contains($closedReason, '用户') ? '用户主动取消' : '管理员取消订单',
        };
    }

    private function formatTemplateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d H:i', $timestamp);
    }

    private function truncateThingField(string $value, int $length = 20): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return mb_substr($trimmed, 0, $length);
    }

    private function composeDisplayText(array $parts, string $separator = ''): string
    {
        $filtered = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $parts
        ), static fn (string $value): bool => $value !== ''));

        return implode($separator, $filtered);
    }

    private function buildOrderDetailPath(array $order): string
    {
        $token = generate_order_access_token((int) ($order['id'] ?? 0), (string) ($order['order_no'] ?? ''));
        if ($token === '') {
            return '/mall/order-detail';
        }

        return '/mall/order-detail?token=' . rawurlencode($token);
    }
}
