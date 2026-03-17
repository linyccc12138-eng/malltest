<?php

declare(strict_types=1);

namespace Mall\Services;

use DateTimeImmutable;
use Mall\Core\DatabaseManager;
use Mall\Core\Logger;
use Mall\Core\RedisClient;
use PDO;

class OrderService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly MembershipService $membership,
        private readonly UserService $users,
        private readonly RedisClient $redis,
        private readonly Logger $logger,
        private readonly ?WechatService $wechat = null,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    public function cart(int $userId): array
    {
        $this->closeExpiredOrders();
        $cartId = $this->getOrCreateCartId($userId);
        $stmt = $this->db->mall()->prepare(
            'SELECT ci.id, ci.product_id, ci.sku_id, ci.quantity, ci.unit_price, ci.member_discount_rate, ci.final_price, ci.item_status,
                    p.name AS product_name, p.cover_image, p.is_on_sale, p.support_member_discount, p.stock_total,
                    ps.attribute_json, ps.stock AS sku_stock, ps.cover_image AS sku_cover_image
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             LEFT JOIN product_skus ps ON ps.id = ci.sku_id
             WHERE ci.cart_id = :cart_id
             ORDER BY ci.id DESC'
        );
        $stmt->execute([':cart_id' => $cartId]);
        $items = $stmt->fetchAll();

        $summary = [
            'subtotal' => 0.0,
            'discount' => 0.0,
            'payable' => 0.0,
        ];

        foreach ($items as &$item) {
            $item['attributes'] = json_decode((string) ($item['attribute_json'] ?? '[]'), true) ?: [];
            $item['cover_image'] = $item['sku_cover_image'] ?: $item['cover_image'];
            $item['sku_stock'] = (int) ($item['sku_stock'] ?? $item['stock_total'] ?? 0);
            $item['quantity'] = (int) $item['quantity'];
            $item['unit_price'] = (float) $item['unit_price'];
            $item['final_price'] = (float) $item['final_price'];

            if ((int) $item['is_on_sale'] !== 1) {
                $item['item_status'] = 'off_shelf';
            } elseif ($item['sku_stock'] < $item['quantity']) {
                $item['item_status'] = 'out_of_stock';
            } else {
                $item['item_status'] = 'valid';
                $summary['subtotal'] += $item['unit_price'] * $item['quantity'];
                $summary['payable'] += $item['final_price'] * $item['quantity'];
            }
        }
        unset($item);

        $summary['discount'] = round($summary['subtotal'] - $summary['payable'], 2);
        $summary['subtotal'] = round($summary['subtotal'], 2);
        $summary['payable'] = round($summary['payable'], 2);

        return [
            'cart_id' => $cartId,
            'items' => $items,
            'summary' => $summary,
        ];
    }

    public function addToCart(int $userId, int $productId, int $skuId, int $quantity): array
    {
        if ($quantity <= 0) {
            throw new \RuntimeException('购买数量必须大于 0。');
        }

        $product = $this->fetchProductSku($productId, $skuId);
        if (!$product || (int) $product['is_on_sale'] !== 1) {
            throw new \RuntimeException('商品已下架或不可购买。');
        }

        $skuStock = (int) ($product['sku_stock'] ?? $product['stock_total']);
        if ($skuStock < $quantity) {
            throw new \RuntimeException('库存不足。');
        }

        $discountRate = (int) $product['support_member_discount'] === 1
            ? $this->membership->getDiscountRateByMallUser($userId)
            : 1.0;
        $unitPrice = (float) ($product['sku_price'] ?? $product['price']);
        $finalPrice = round($unitPrice * $discountRate, 2);

        $cartId = $this->getOrCreateCartId($userId);
        $pdo = $this->db->mall();
        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND sku_id = :sku_id');
        $stmt->execute([':cart_id' => $cartId, ':product_id' => $productId, ':sku_id' => $skuId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $newQuantity = (int) $existing['quantity'] + $quantity;
            if ($newQuantity > $skuStock) {
                throw new \RuntimeException('加入后数量超过库存。');
            }

            $update = $pdo->prepare(
                'UPDATE cart_items
                 SET quantity = :quantity, unit_price = :unit_price, member_discount_rate = :member_discount_rate,
                     final_price = :final_price, item_status = :item_status, updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':quantity' => $newQuantity,
                ':unit_price' => $unitPrice,
                ':member_discount_rate' => $discountRate,
                ':final_price' => $finalPrice,
                ':item_status' => 'valid',
                ':updated_at' => now(),
                ':id' => (int) $existing['id'],
            ]);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO cart_items (cart_id, product_id, sku_id, quantity, unit_price, member_discount_rate, final_price, item_status, created_at, updated_at)
                 VALUES (:cart_id, :product_id, :sku_id, :quantity, :unit_price, :member_discount_rate, :final_price, :item_status, :created_at, :updated_at)'
            );
            $insert->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId,
                ':sku_id' => $skuId,
                ':quantity' => $quantity,
                ':unit_price' => $unitPrice,
                ':member_discount_rate' => $discountRate,
                ':final_price' => $finalPrice,
                ':item_status' => 'valid',
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }

        return $this->cart($userId);
    }

    public function updateCartItem(int $userId, int $itemId, int $quantity): array
    {
        if ($quantity <= 0) {
            $this->removeCartItem($userId, $itemId);
            return $this->cart($userId);
        }

        $cartId = $this->getOrCreateCartId($userId);
        $stmt = $this->db->mall()->prepare('SELECT product_id, sku_id FROM cart_items WHERE id = :id AND cart_id = :cart_id');
        $stmt->execute([':id' => $itemId, ':cart_id' => $cartId]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new \RuntimeException('购物车商品不存在。');
        }

        $product = $this->fetchProductSku((int) $item['product_id'], (int) $item['sku_id']);
        if (!$product) {
            throw new \RuntimeException('商品不存在。');
        }

        $stock = (int) ($product['sku_stock'] ?? $product['stock_total']);
        if ($stock < $quantity) {
            throw new \RuntimeException('库存不足。');
        }

        $discountRate = (int) $product['support_member_discount'] === 1
            ? $this->membership->getDiscountRateByMallUser($userId)
            : 1.0;
        $unitPrice = (float) ($product['sku_price'] ?? $product['price']);
        $finalPrice = round($unitPrice * $discountRate, 2);

        $update = $this->db->mall()->prepare(
            'UPDATE cart_items
             SET quantity = :quantity, unit_price = :unit_price, member_discount_rate = :member_discount_rate,
                 final_price = :final_price, item_status = :item_status, updated_at = :updated_at
             WHERE id = :id AND cart_id = :cart_id'
        );
        $update->execute([
            ':quantity' => $quantity,
            ':unit_price' => $unitPrice,
            ':member_discount_rate' => $discountRate,
            ':final_price' => $finalPrice,
            ':item_status' => 'valid',
            ':updated_at' => now(),
            ':id' => $itemId,
            ':cart_id' => $cartId,
        ]);

        return $this->cart($userId);
    }

    public function removeCartItem(int $userId, int $itemId): void
    {
        $cartId = $this->getOrCreateCartId($userId);
        $stmt = $this->db->mall()->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :cart_id');
        $stmt->execute([':id' => $itemId, ':cart_id' => $cartId]);
    }
    public function createOrderFromCart(int $userId, int $addressId, array $selectedItemIds = []): array
    {
        $this->closeExpiredOrders();
        $user = $this->users->findUser($userId);
        if (!$user) {
            throw new \RuntimeException('用户不存在。');
        }

        $address = $this->users->findAddress($userId, $addressId);
        if (!$address) {
            throw new \RuntimeException('请选择有效的收货地址。');
        }

        $cart = $this->cart($userId);
        $items = $cart['items'];
        if ($selectedItemIds !== []) {
            $selectedMap = array_map('intval', $selectedItemIds);
            $items = array_values(array_filter($items, static fn (array $item): bool => in_array((int) $item['id'], $selectedMap, true)));
        }

        $validItems = array_values(array_filter($items, static fn (array $item): bool => $item['item_status'] === 'valid'));
        if ($validItems === []) {
            throw new \RuntimeException('购物车中没有可下单商品。');
        }

        $discountRate = $this->membership->getDiscountRateByMallUser($userId);
        $memberSnapshot = $this->membership->getMallUserMember($userId);
        $pdo = $this->db->mall();
        $pdo->beginTransaction();

        try {
            $subtotal = 0.0;
            $payable = 0.0;
            $orderNo = $this->generateOrderNo();
            $expiresAt = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');
            $lockedItems = [];

            foreach ($validItems as $item) {
                $locked = $this->fetchProductSkuForUpdate($pdo, (int) $item['product_id'], (int) $item['sku_id']);
                if (!$locked || (int) $locked['is_on_sale'] !== 1) {
                    throw new \RuntimeException('下单时检测到商品已下架，请刷新后重试。');
                }

                $stock = (int) ($locked['sku_stock'] ?? $locked['stock_total']);
                if ($stock < (int) $item['quantity']) {
                    throw new \RuntimeException('下单时检测到库存变化，请刷新后重试。');
                }

                $lockedItems[] = $locked;
            }

            foreach ($validItems as $index => $item) {
                $locked = $lockedItems[$index];
                $unitPrice = (float) ($locked['sku_price'] ?? $locked['price']);
                $finalUnitPrice = (int) $locked['support_member_discount'] === 1 ? round($unitPrice * $discountRate, 2) : $unitPrice;
                $subtotal += $unitPrice * (int) $item['quantity'];
                $payable += $finalUnitPrice * (int) $item['quantity'];
            }

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (order_no, user_id, address_snapshot_json, status, payment_status, payment_method,
                    subtotal_amount, discount_amount, payable_amount, paid_amount, member_discount_rate, membership_snapshot_json,
                    placed_at, expires_at, created_at, updated_at)
                 VALUES (:order_no, :user_id, :address_snapshot_json, :status, :payment_status, :payment_method,
                    :subtotal_amount, :discount_amount, :payable_amount, :paid_amount, :member_discount_rate, :membership_snapshot_json,
                    :placed_at, :expires_at, :created_at, :updated_at)'
            );
            $orderStmt->execute([
                ':order_no' => $orderNo,
                ':user_id' => $userId,
                ':address_snapshot_json' => json_encode_unicode($address),
                ':status' => 'pending_payment',
                ':payment_status' => 'unpaid',
                ':payment_method' => 'unselected',
                ':subtotal_amount' => round($subtotal, 2),
                ':discount_amount' => round($subtotal - $payable, 2),
                ':payable_amount' => round($payable, 2),
                ':paid_amount' => 0,
                ':member_discount_rate' => $discountRate,
                ':membership_snapshot_json' => json_encode_unicode($memberSnapshot ?: []),
                ':placed_at' => now(),
                ':expires_at' => $expiresAt,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, sku_id, product_name, sku_name, quantity, unit_price, final_unit_price,
                    line_total, support_member_discount, cover_image, attribute_snapshot_json, created_at, updated_at)
                 VALUES (:order_id, :product_id, :sku_id, :product_name, :sku_name, :quantity, :unit_price, :final_unit_price,
                    :line_total, :support_member_discount, :cover_image, :attribute_snapshot_json, :created_at, :updated_at)'
            );
            $updateSkuStmt = $pdo->prepare('UPDATE product_skus SET stock = stock - :quantity, updated_at = :updated_at WHERE id = :sku_id');
            $updateProductStmt = $pdo->prepare('UPDATE products SET stock_total = stock_total - :quantity, updated_at = :updated_at WHERE id = :product_id');
            $deleteCartStmt = $pdo->prepare('DELETE FROM cart_items WHERE id = :id');

            foreach ($validItems as $index => $item) {
                $locked = $lockedItems[$index];
                $attributes = json_decode((string) ($locked['attribute_json'] ?? '[]'), true) ?: [];
                $unitPrice = (float) ($locked['sku_price'] ?? $locked['price']);
                $finalUnitPrice = (int) $locked['support_member_discount'] === 1 ? round($unitPrice * $discountRate, 2) : $unitPrice;
                $quantity = (int) $item['quantity'];

                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => (int) $item['product_id'],
                    ':sku_id' => (int) $item['sku_id'],
                    ':product_name' => $locked['name'],
                    ':sku_name' => implode(' / ', array_values($attributes)),
                    ':quantity' => $quantity,
                    ':unit_price' => $unitPrice,
                    ':final_unit_price' => $finalUnitPrice,
                    ':line_total' => round($finalUnitPrice * $quantity, 2),
                    ':support_member_discount' => (int) $locked['support_member_discount'],
                    ':cover_image' => $locked['sku_cover_image'] ?: $locked['cover_image'],
                    ':attribute_snapshot_json' => json_encode_unicode($attributes),
                    ':created_at' => now(),
                    ':updated_at' => now(),
                ]);

                $updateSkuStmt->execute([':quantity' => $quantity, ':updated_at' => now(), ':sku_id' => (int) $item['sku_id']]);
                $updateProductStmt->execute([':quantity' => $quantity, ':updated_at' => now(), ':product_id' => (int) $item['product_id']]);
                $deleteCartStmt->execute([':id' => (int) $item['id']]);
            }

            $pdo->commit();
            $this->setOrderExpireKey($orderNo, $orderId);

            $order = $this->findOrder($orderId, $userId, true);
            if ($order && $this->notifications) {
                $this->notifications->notifyUser('created', $order, $user);
            }

            return $order ?? [];
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function payWithBalance(int $userId, int $orderId): array
    {
        $this->closeExpiredOrders();
        $user = $this->users->findUser($userId);
        if (!$user) {
            throw new \RuntimeException('用户不存在。');
        }
        if (empty($user['membership_member_id'])) {
            throw new \RuntimeException('当前用户未绑定会员，无法使用余额支付。');
        }

        $order = $this->findOrder($orderId, $userId, true);
        if (!$order || $order['status'] !== 'pending_payment') {
            throw new \RuntimeException('订单当前不可使用余额支付。');
        }

        $goodsNames = $this->goodsNamesFromOrder($order);
        $memberId = (int) $user['membership_member_id'];
        $amount = (float) $order['payable_amount'];
        $memberPaid = false;

        try {
            $member = $this->membership->consumeForOrder($memberId, $goodsNames, $amount, '电商网站自助下单');
            $memberPaid = true;

            $pdo = $this->db->mall();
            $pdo->beginTransaction();
            $paymentNo = $this->generatePaymentNo();
            $this->markOrderPaidInTransaction($pdo, $orderId, 'balance', $paymentNo, json_encode_unicode(['member' => $member]));
            $this->insertWalletLedger($pdo, $userId, $orderId, 'mall_consume', $amount, '商城订单余额支付');
            $pdo->commit();
            $this->clearOrderExpireKey((string) $order['order_no']);

            $paidOrder = $this->findOrder($orderId, $userId, true);
            if ($paidOrder && $this->notifications) {
                $this->notifications->notifyAdmins('paid', $paidOrder);
                $this->notifications->notifyUser('paid', $paidOrder, $user);
            }

            return $paidOrder ?? [];
        } catch (\Throwable $throwable) {
            if ($memberPaid) {
                $this->membership->compensate($memberId, $amount, '商城余额支付失败回滚', $goodsNames);
            }
            throw $throwable;
        }
    }

    public function startWechatPay(int $userId, int $orderId): array
    {
        if (!$this->wechat) {
            throw new \RuntimeException('微信支付服务未启用。');
        }

        $user = $this->users->findUser($userId);
        $order = $this->findOrder($orderId, $userId, true);
        if (!$order || !$user || $order['status'] !== 'pending_payment') {
            throw new \RuntimeException('订单当前不可发起微信支付。');
        }

        $paymentNo = $this->generatePaymentNo();
        $payPayload = $this->wechat->createPayOrder($order, $user);

        $pdo = $this->db->mall();
        $stmt = $pdo->prepare(
            'INSERT INTO payments (order_id, payment_no, method, status, amount, transaction_no, request_payload_json, response_payload_json, created_at, updated_at)
             VALUES (:order_id, :payment_no, :method, :status, :amount, :transaction_no, :request_payload_json, :response_payload_json, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':payment_no' => $paymentNo,
            ':method' => 'wechat',
            ':status' => 'pending',
            ':amount' => (float) $order['payable_amount'],
            ':transaction_no' => '',
            ':request_payload_json' => json_encode_unicode(['order_no' => $order['order_no']]),
            ':response_payload_json' => json_encode_unicode($payPayload),
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return $payPayload;
    }

    public function handleWechatNotify(array $headers, string $body): array
    {
        if (!$this->wechat) {
            throw new \RuntimeException('微信支付服务未启用。');
        }

        $notify = $this->wechat->handlePayNotify($headers, $body);
        $orderNo = (string) ($notify['order_no'] ?? '');
        if ($orderNo === '') {
            throw new \RuntimeException('微信支付通知缺少订单号。');
        }

        return $this->markWechatOrderPaid($orderNo, $notify['payload']);
    }
    public function userOrders(int $userId, string $group = 'all', int $page = 1, int $pageSize = 15): array
    {
        $this->closeExpiredOrders();
        return $this->listOrders(['user_id' => $userId, 'group' => $group, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function adminOrders(string $group = 'all', int $page = 1, int $pageSize = 15): array
    {
        $this->closeExpiredOrders();
        return $this->listOrders(['group' => $group, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function cancelOrder(int $userId, int $orderId): array
    {
        $order = $this->findOrder($orderId, $userId, true);
        if (!$order) {
            throw new \RuntimeException('订单不存在。');
        }
        if (!in_array($order['status'], ['pending_payment', 'pending_shipment'], true)) {
            throw new \RuntimeException('当前订单不可取消。');
        }

        $this->closeOrderInternal($orderId, 'user_cancelled', '用户取消订单');
        $user = $this->users->findUser($userId);
        $closed = $this->findOrder($orderId, $userId, true);
        if ($closed && $this->notifications && $user) {
            $this->notifications->notifyAdmins('cancelled', $closed);
            $this->notifications->notifyUser('closed', $closed, $user);
        }

        return $closed ?? [];
    }

    public function shipOrder(int $orderId, string $shippingCompany, string $shippingNo): array
    {
        if ($shippingCompany === '' || $shippingNo === '') {
            throw new \RuntimeException('请填写物流公司和运单号。');
        }

        $stmt = $this->db->mall()->prepare(
            'UPDATE orders
             SET status = :status, shipped_at = :shipped_at, shipping_company = :shipping_company, shipping_no = :shipping_no, updated_at = :updated_at
             WHERE id = :id AND status = :current_status AND payment_status = :payment_status'
        );
        $stmt->execute([
            ':status' => 'pending_receipt',
            ':shipped_at' => now(),
            ':shipping_company' => $shippingCompany,
            ':shipping_no' => $shippingNo,
            ':updated_at' => now(),
            ':id' => $orderId,
            ':current_status' => 'pending_shipment',
            ':payment_status' => 'paid',
        ]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('订单当前不可发货。');
        }

        $order = $this->findOrder($orderId, null, true);
        if ($order && $this->notifications) {
            $user = $this->users->findUser((int) $order['user_id']);
            if ($user) {
                $this->notifications->notifyUser('shipped', $order, $user);
            }
        }

        return $order ?? [];
    }

    public function completeOrder(int $userId, int $orderId): array
    {
        $stmt = $this->db->mall()->prepare(
            'UPDATE orders
             SET status = :status, completed_at = :completed_at, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id AND status = :current_status'
        );
        $stmt->execute([
            ':status' => 'completed',
            ':completed_at' => now(),
            ':updated_at' => now(),
            ':id' => $orderId,
            ':user_id' => $userId,
            ':current_status' => 'pending_receipt',
        ]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('订单当前不可确认收货。');
        }

        $order = $this->findOrder($orderId, $userId, true);
        if ($order && $this->notifications) {
            $user = $this->users->findUser($userId);
            if ($user) {
                $this->notifications->notifyUser('completed', $order, $user);
            }
        }

        return $order ?? [];
    }

    public function closeOrderByAdmin(int $orderId, string $reason): array
    {
        $this->closeOrderInternal($orderId, 'admin_closed', $reason === '' ? '管理后台关闭订单' : $reason);
        $order = $this->findOrder($orderId, null, true);
        if ($order && $this->notifications) {
            $user = $this->users->findUser((int) $order['user_id']);
            if ($user) {
                $this->notifications->notifyUser('closed', $order, $user);
            }
        }

        return $order ?? [];
    }

    public function walletRecords(int $userId): array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, order_id, type, amount, remark, created_at
             FROM wallet_ledger
             WHERE user_id = :user_id
             ORDER BY id DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function closeExpiredOrders(): void
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id FROM orders WHERE status = :status AND expires_at IS NOT NULL AND expires_at <= :now'
        );
        $stmt->execute([':status' => 'pending_payment', ':now' => now()]);

        foreach ($stmt->fetchAll() as $order) {
            $this->closeOrderInternal((int) $order['id'], 'timeout', '订单支付超时自动关闭');
        }
    }

    public function findOrder(int $orderId, ?int $userId = null, bool $withItems = false): ?array
    {
        $sql = 'SELECT * FROM orders WHERE id = :id';
        $params = [':id' => $orderId];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->db->mall()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['subtotal_amount'] = (float) $row['subtotal_amount'];
        $row['discount_amount'] = (float) $row['discount_amount'];
        $row['payable_amount'] = (float) $row['payable_amount'];
        $row['paid_amount'] = (float) $row['paid_amount'];
        $row['address_snapshot'] = json_decode((string) ($row['address_snapshot_json'] ?? '[]'), true) ?: [];
        $row['membership_snapshot'] = json_decode((string) ($row['membership_snapshot_json'] ?? '[]'), true) ?: [];

        if ($withItems) {
            $itemStmt = $this->db->mall()->prepare(
                'SELECT id, product_id, sku_id, product_name, sku_name, quantity, unit_price, final_unit_price, line_total, cover_image, attribute_snapshot_json
                 FROM order_items
                 WHERE order_id = :order_id
                 ORDER BY id ASC'
            );
            $itemStmt->execute([':order_id' => $orderId]);
            $row['items'] = array_map(static function (array $item): array {
                $item['quantity'] = (int) $item['quantity'];
                $item['unit_price'] = (float) $item['unit_price'];
                $item['final_unit_price'] = (float) $item['final_unit_price'];
                $item['line_total'] = (float) $item['line_total'];
                $item['attributes'] = json_decode((string) ($item['attribute_snapshot_json'] ?? '[]'), true) ?: [];
                return $item;
            }, $itemStmt->fetchAll());
        }

        return $row;
    }

    private function listOrders(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($filters['page_size'] ?? 15)));
        $offset = ($page - 1) * $pageSize;

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        $group = $filters['group'] ?? 'all';
        $groupMap = [
            'pending_payment' => ['pending_payment'],
            'pending_shipment' => ['pending_shipment'],
            'pending_receipt' => ['pending_receipt'],
            'completed' => ['completed'],
            'paid' => ['pending_shipment'],
            'shipped' => ['pending_receipt', 'completed'],
            'closed' => ['closed'],
        ];
        if ($group !== 'all' && isset($groupMap[$group])) {
            $statuses = $groupMap[$group];
            $placeholders = [];
            foreach ($statuses as $index => $status) {
                $key = ':status_' . $index;
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $where[] = 'status IN (' . implode(', ', $placeholders) . ')';
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->db->mall()->prepare('SELECT COUNT(*) AS total FROM orders WHERE ' . $whereSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

        $stmt = $this->db->mall()->prepare('SELECT * FROM orders WHERE ' . $whereSql . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $items = array_map(function (array $order): array {
            $order['subtotal_amount'] = (float) $order['subtotal_amount'];
            $order['discount_amount'] = (float) $order['discount_amount'];
            $order['payable_amount'] = (float) $order['payable_amount'];
            $order['paid_amount'] = (float) $order['paid_amount'];
            $order['address_snapshot'] = json_decode((string) ($order['address_snapshot_json'] ?? '[]'), true) ?: [];
            return $order;
        }, $rows);

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ];
    }
    private function closeOrderInternal(int $orderId, string $closedReason, string $logReason): void
    {
        $order = $this->findOrder($orderId, null, true);
        if (!$order) {
            throw new \RuntimeException('订单不存在。');
        }
        if (in_array($order['status'], ['closed', 'completed'], true)) {
            return;
        }
        if ($order['status'] === 'pending_receipt') {
            throw new \RuntimeException('已发货订单不可关闭。');
        }
        if (!in_array($order['status'], ['pending_payment', 'pending_shipment'], true)) {
            throw new \RuntimeException('当前订单不可关闭。');
        }

        $user = $this->users->findUser((int) $order['user_id']);
        $goodsNames = $this->goodsNamesFromOrder($order);
        $refundAmount = (float) $order['paid_amount'];
        $memberRefunded = false;
        $memberId = 0;

        if ($order['payment_status'] === 'paid' && $order['payment_method'] === 'balance' && $refundAmount > 0) {
            if (!$user || empty($user['membership_member_id'])) {
                throw new \RuntimeException('余额支付订单缺少会员绑定信息，无法关闭。');
            }
            $memberId = (int) $user['membership_member_id'];
            $this->membership->compensate($memberId, $refundAmount, '商城订单取消退回余额', $goodsNames);
            $memberRefunded = true;
        }

        $pdo = $this->db->mall();
        $pdo->beginTransaction();

        try {
            $locked = $this->findOrderForUpdate($pdo, $orderId);
            if (!$locked) {
                throw new \RuntimeException('订单不存在。');
            }
            if (in_array($locked['status'], ['closed', 'completed'], true)) {
                $pdo->rollBack();
                return;
            }
            if ($locked['status'] === 'pending_receipt') {
                throw new \RuntimeException('已发货订单不可关闭。');
            }

            $this->restoreInventory($pdo, $orderId);

            $paymentStatus = 'unpaid';
            if ($locked['payment_status'] === 'paid') {
                $paymentStatus = $locked['payment_method'] === 'balance' ? 'refunded' : 'refund_pending';
            }

            $stmt = $pdo->prepare(
                'UPDATE orders
                 SET status = :status, payment_status = :payment_status, closed_reason = :closed_reason, closed_at = :closed_at, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status' => 'closed',
                ':payment_status' => $paymentStatus,
                ':closed_reason' => $closedReason,
                ':closed_at' => now(),
                ':updated_at' => now(),
                ':id' => $orderId,
            ]);

            if ($locked['payment_status'] === 'paid' && $refundAmount > 0) {
                if ($locked['payment_method'] === 'balance') {
                    $this->insertWalletLedger($pdo, (int) $locked['user_id'], $orderId, 'mall_refund_balance', $refundAmount, '订单关闭退回会员余额');
                } elseif ($locked['payment_method'] === 'wechat') {
                    $this->insertWalletLedger($pdo, (int) $locked['user_id'], $orderId, 'wechat_refund_notice', 0, '微信支付订单已关闭，请在商户平台人工退款');
                }
            }

            $pdo->commit();
            $this->clearOrderExpireKey((string) $locked['order_no']);
            $this->logger->info('order', '订单已关闭', ['order_id' => $orderId, 'reason' => $logReason]);
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            if ($memberRefunded && $memberId > 0 && $refundAmount > 0) {
                $this->membership->consumeForOrder($memberId, $goodsNames, $refundAmount, '订单关闭回滚退款');
            }
            throw $throwable;
        }
    }

    private function restoreInventory(PDO $pdo, int $orderId): void
    {
        $stmt = $pdo->prepare('SELECT product_id, sku_id, quantity FROM order_items WHERE order_id = :order_id');
        $stmt->execute([':order_id' => $orderId]);

        $skuStmt = $pdo->prepare('UPDATE product_skus SET stock = stock + :quantity, updated_at = :updated_at WHERE id = :sku_id');
        $productStmt = $pdo->prepare('UPDATE products SET stock_total = stock_total + :quantity, updated_at = :updated_at WHERE id = :product_id');
        foreach ($stmt->fetchAll() as $item) {
            $skuStmt->execute([':quantity' => (int) $item['quantity'], ':updated_at' => now(), ':sku_id' => (int) $item['sku_id']]);
            $productStmt->execute([':quantity' => (int) $item['quantity'], ':updated_at' => now(), ':product_id' => (int) $item['product_id']]);
        }
    }

    private function findOrderForUpdate(PDO $pdo, int $orderId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchProductSku(int $productId, int $skuId): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT p.id, p.name, p.price, p.cover_image, p.stock_total, p.is_on_sale, p.support_member_discount,
                    ps.id AS sku_id, ps.price AS sku_price, ps.stock AS sku_stock, ps.cover_image AS sku_cover_image, ps.attribute_json
             FROM products p
             LEFT JOIN product_skus ps ON ps.id = :sku_id AND ps.product_id = p.id
             WHERE p.id = :product_id
             LIMIT 1'
        );
        $stmt->execute([':product_id' => $productId, ':sku_id' => $skuId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchProductSkuForUpdate(PDO $pdo, int $productId, int $skuId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.price, p.cover_image, p.stock_total, p.is_on_sale, p.support_member_discount,
                    ps.id AS sku_id, ps.price AS sku_price, ps.stock AS sku_stock, ps.cover_image AS sku_cover_image, ps.attribute_json
             FROM products p
             LEFT JOIN product_skus ps ON ps.id = :sku_id AND ps.product_id = p.id
             WHERE p.id = :product_id
             FOR UPDATE'
        );
        $stmt->execute([':product_id' => $productId, ':sku_id' => $skuId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getOrCreateCartId(int $userId): int
    {
        $stmt = $this->db->mall()->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        $insert = $this->db->mall()->prepare('INSERT INTO carts (user_id, created_at, updated_at) VALUES (:user_id, :created_at, :updated_at)');
        $insert->execute([':user_id' => $userId, ':created_at' => now(), ':updated_at' => now()]);
        return (int) $this->db->mall()->lastInsertId();
    }

    private function generateOrderNo(): string
    {
        return 'MM' . date('YmdHis') . random_int(1000, 9999);
    }

    private function generatePaymentNo(): string
    {
        return 'PAY' . date('YmdHis') . random_int(1000, 9999);
    }

    private function markOrderPaidInTransaction(PDO $pdo, int $orderId, string $method, string $paymentNo, string $payloadJson): void
    {
        $order = $this->findOrderForUpdate($pdo, $orderId);
        if (!$order || $order['status'] !== 'pending_payment') {
            throw new \RuntimeException('订单状态不允许支付。');
        }

        $update = $pdo->prepare(
            'UPDATE orders
             SET status = :status, payment_status = :payment_status, payment_method = :payment_method, paid_amount = :paid_amount,
                 paid_at = :paid_at, updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':status' => 'pending_shipment',
            ':payment_status' => 'paid',
            ':payment_method' => $method,
            ':paid_amount' => (float) $order['payable_amount'],
            ':paid_at' => now(),
            ':updated_at' => now(),
            ':id' => $orderId,
        ]);

        $payment = $pdo->prepare(
            'INSERT INTO payments (order_id, payment_no, method, status, amount, transaction_no, request_payload_json, response_payload_json, created_at, updated_at)
             VALUES (:order_id, :payment_no, :method, :status, :amount, :transaction_no, :request_payload_json, :response_payload_json, :created_at, :updated_at)'
        );
        $payment->execute([
            ':order_id' => $orderId,
            ':payment_no' => $paymentNo,
            ':method' => $method,
            ':status' => 'success',
            ':amount' => (float) $order['payable_amount'],
            ':transaction_no' => $paymentNo,
            ':request_payload_json' => '{}',
            ':response_payload_json' => $payloadJson,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    private function insertWalletLedger(PDO $pdo, int $userId, int $orderId, string $type, float $amount, string $remark): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO wallet_ledger (user_id, order_id, type, amount, remark, created_at)
             VALUES (:user_id, :order_id, :type, :amount, :remark, :created_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':order_id' => $orderId,
            ':type' => $type,
            ':amount' => $amount,
            ':remark' => $remark,
            ':created_at' => now(),
        ]);
    }

    private function setOrderExpireKey(string $orderNo, int $orderId): void
    {
        if ($this->redis->isAvailable()) {
            $this->redis->setex('order:expire:' . $orderNo, 900, (string) $orderId);
        }
    }

    private function clearOrderExpireKey(string $orderNo): void
    {
        if ($this->redis->isAvailable()) {
            $this->redis->del('order:expire:' . $orderNo);
        }
    }

    private function markWechatOrderPaid(string $orderNo, array $payload): array
    {
        $pdo = $this->db->mall();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT id, user_id FROM orders WHERE order_no = :order_no FOR UPDATE');
            $stmt->execute([':order_no' => $orderNo]);
            $order = $stmt->fetch();
            if (!$order) {
                throw new \RuntimeException('订单不存在。');
            }

            $current = $this->findOrder((int) $order['id'], (int) $order['user_id'], true);
            if (!$current) {
                throw new \RuntimeException('订单不存在。');
            }
            if ($current['status'] !== 'pending_payment') {
                $pdo->rollBack();
                return $current;
            }

            $paymentNo = $this->generatePaymentNo();
            $this->markOrderPaidInTransaction($pdo, (int) $order['id'], 'wechat', $paymentNo, json_encode_unicode($payload));
            $pdo->commit();
            $this->clearOrderExpireKey($orderNo);

            $fullOrder = $this->findOrder((int) $order['id'], (int) $order['user_id'], true);
            if ($fullOrder && $this->notifications) {
                $user = $this->users->findUser((int) $order['user_id']);
                if ($user) {
                    $this->notifications->notifyAdmins('paid', $fullOrder);
                    $this->notifications->notifyUser('paid', $fullOrder, $user);
                }
            }

            return $fullOrder ?? [];
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    private function goodsNamesFromOrder(array $order): string
    {
        $items = $order['items'] ?? [];
        return implode(',', array_column($items, 'product_name'));
    }
}
