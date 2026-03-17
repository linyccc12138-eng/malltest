<?php

declare(strict_types=1);

namespace Mall\Controllers;

use Mall\Core\ConfirmRequiredException;
use Mall\Core\DatabaseManager;
use Mall\Core\Request;
use Mall\Core\Response;
use Mall\Services\CatalogService;
use Mall\Services\DashboardService;
use Mall\Services\MembershipService;
use Mall\Services\OrderService;
use Mall\Services\SettingsService;
use Mall\Services\UserService;
use Mall\Services\WechatService;

class AdminApiController extends BaseController
{
    public function __construct(
        \Mall\Core\App $app,
        private readonly DashboardService $dashboard,
        private readonly CatalogService $catalog,
        private readonly UserService $users,
        private readonly MembershipService $membership,
        private readonly OrderService $orders,
        private readonly SettingsService $settings,
        private readonly WechatService $wechat,
        private readonly DatabaseManager $db
    ) {
        parent::__construct($app);
    }

    public function dashboard(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->dashboard->overview());
    }

    public function products(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->catalog->searchProducts(array_merge($request->all(), ['include_off_sale' => 1])));
    }

    public function saveProduct(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $productId = isset($params['id']) ? (int) $params['id'] : null;
            return $this->catalog->saveProduct($request->all(), $productId);
        });
    }

    public function batchProducts(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request): array {
            $this->validateCsrf($request);
            $ids = $request->input('product_ids', []);
            $ids = is_array($ids) ? $ids : [];
            $this->catalog->batchUpdateProducts($ids, (string) $request->input('action', ''), $request->input('value'));

            return ['message' => '商品批量操作已完成。'];
        });
    }

    public function categories(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->catalog->categoriesTree());
    }

    public function saveCategory(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $categoryId = isset($params['id']) ? (int) $params['id'] : null;
            return $this->catalog->saveCategory($request->all(), $categoryId);
        });
    }

    public function sortCategories(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request): array {
            $this->validateCsrf($request);
            $items = $request->input('items', []);
            $items = is_array($items) ? $items : [];
            $this->catalog->sortCategories($items);

            return ['message' => '分类排序已更新。'];
        });
    }

    public function activities(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->catalog->listActivities(false));
    }

    public function saveActivity(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $activityId = isset($params['id']) ? (int) $params['id'] : null;
            return $this->catalog->saveActivity($request->all(), $activityId);
        });
    }

    public function users(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->users->listUsers(
            (int) $request->input('page', 1),
            (int) $request->input('page_size', 15)
        ));
    }

    public function saveUser(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $userId = isset($params['id']) ? (int) $params['id'] : null;

            if ($userId) {
                return $this->users->updateUser($userId, $request->all());
            }

            return $this->users->createUser($request->all());
        });
    }

    public function members(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->membership->searchMembers(
            (string) $request->input('keyword', ''),
            (int) $request->input('page', 1),
            (int) $request->input('page_size', 15)
        ));
    }

    public function memberClasses(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->membership->getClasses());
    }

    public function saveMember(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $memberId = isset($params['id']) ? (int) $params['id'] : null;

            if ($memberId) {
                return $this->membership->updateMember($memberId, $request->all());
            }

            return $this->membership->createMember($request->all());
        });
    }

    public function adjustMemberBalance(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            return $this->membership->adjustBalance(
                (int) ($params['id'] ?? 0),
                (float) $request->input('amount', 0),
                (string) $request->input('mark', '管理后台手动调整余额'),
                (string) $request->input('goods', '')
            );
        });
    }

    public function orders(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->orders->adminOrders(
            (string) $request->input('group', 'all'),
            (int) $request->input('page', 1),
            (int) $request->input('page_size', 15)
        ));
    }

    public function shipOrder(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            return $this->orders->shipOrder(
                (int) ($params['id'] ?? 0),
                (string) $request->input('shipping_company', ''),
                (string) $request->input('shipping_no', '')
            );
        });
    }

    public function closeOrder(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            return $this->orders->closeOrderByAdmin(
                (int) ($params['id'] ?? 0),
                (string) $request->input('reason', '管理后台关闭订单')
            );
        });
    }

    public function settings(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->settings->allForAdmin());
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $group = (string) ($params['group'] ?? '');
            if ($group === '') {
                throw new \RuntimeException('缺少配置分组。');
            }

            $encryptedMap = [
                'membership_mysql' => ['password'],
                'wechat_pay' => ['api_v3_key', 'private_key_content', 'platform_cert_content'],
                'wechat_service_account' => ['app_secret'],
                'log' => [],
                'notifications' => [],
            ];

            $values = $request->all();
            unset($values['_csrf_token']);
            $this->settings->saveGroup($group, $values, $encryptedMap[$group] ?? []);

            return ['message' => '配置已保存。'];
        });
    }

    public function testMembership(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request): array {
            $this->validateCsrf($request);
            return $this->settings->testMembershipConnection($request->all());
        });
    }

    public function testWechatPay(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request): array {
            $this->validateCsrf($request);
            return $this->wechat->testPayConfig($request->all());
        });
    }

    public function testServiceAccount(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request): array {
            $this->validateCsrf($request);
            return $this->wechat->testServiceAccount($request->all());
        });
    }

    public function logs(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request): array {
            $where = ['1 = 1'];
            $bindings = [];
            $page = max(1, (int) $request->input('page', 1));
            $pageSize = max(1, min(100, (int) $request->input('page_size', 15)));
            $offset = ($page - 1) * $pageSize;

            if ($request->input('level')) {
                $where[] = 'level = :level';
                $bindings[':level'] = (string) $request->input('level');
            }

            if ($request->input('channel')) {
                $where[] = 'channel = :channel';
                $bindings[':channel'] = (string) $request->input('channel');
            }

            $countSql = 'SELECT COUNT(*) AS total
                    FROM system_logs
                    WHERE ' . implode(' AND ', $where);
            $countStmt = $this->db->mall()->prepare($countSql);
            foreach ($bindings as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $total = (int) (($countStmt->fetch()['total'] ?? 0));

            $sql = 'SELECT id, level, channel, message, context_json, user_id, ip_address, created_at
                    FROM system_logs
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY id DESC
                    LIMIT :limit OFFSET :offset';
            $stmt = $this->db->mall()->prepare($sql);
            foreach ($bindings as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $levels = $this->db->mall()->query('SELECT DISTINCT level FROM system_logs ORDER BY level ASC')->fetchAll();
            $channels = $this->db->mall()->query('SELECT DISTINCT channel FROM system_logs ORDER BY channel ASC')->fetchAll();

            return [
                'items' => $stmt->fetchAll(),
                'levels' => array_values(array_filter(array_map(static fn (array $item): string => (string) $item['level'], $levels))),
                'channels' => array_values(array_filter(array_map(static fn (array $item): string => (string) $item['channel'], $channels))),
                'meta' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => $total,
                    'total_pages' => max(1, (int) ceil($total / $pageSize)),
                ],
            ];
        });
    }

    public function upload(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request): array {
            $this->validateCsrf($request);
            $this->users->requireAdmin();

            if (empty($request->files['file']['tmp_name'])) {
                throw new \RuntimeException('未接收到上传文件。');
            }

            $uploadDir = base_path('public/assets/images/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $extension = pathinfo((string) $request->files['file']['name'], PATHINFO_EXTENSION) ?: 'png';
            $filename = 'editor-' . date('YmdHis') . '-' . random_int(1000, 9999) . '.' . strtolower($extension);
            $destination = $uploadDir . '/' . $filename;
            move_uploaded_file($request->files['file']['tmp_name'], $destination);

            return ['location' => '/assets/images/uploads/' . $filename];
        });
    }

    private function adminRespond(callable $callback): Response
    {
        try {
            $this->users->requireAdmin();
            return $this->json(['success' => true, 'data' => $callback()]);
        } catch (ConfirmRequiredException $exception) {
            return $this->json([
                'success' => false,
                'error_code' => 'confirm_required',
                'message' => $exception->getMessage(),
                'data' => $exception->payload(),
            ], 409);
        } catch (\Throwable $throwable) {
            return $this->json([
                'success' => false,
                'message' => $throwable->getMessage(),
            ], 400);
        }
    }
}
