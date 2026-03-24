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
        return $this->adminRespond(function () use ($request): array {
            $payload = $this->users->listUsers(
                (int) $request->input('page', 1),
                (int) $request->input('page_size', 15)
            );

            $memberIds = array_values(array_unique(array_filter(array_map(
                static fn (array $item): int => (int) ($item['membership_member_id'] ?? 0),
                (array) ($payload['items'] ?? [])
            ))));
            $memberMap = $this->membership->getMembersByIds($memberIds);

            $payload['items'] = array_map(function (array $item) use ($memberMap): array {
                $memberId = (int) ($item['membership_member_id'] ?? 0);
                $member = $memberMap[$memberId] ?? null;
                $item['wechat_bound'] = (int) ($item['wechat_bound'] ?? 0) === 1;
                $item['member_profile'] = $member ? [
                    'id' => (int) ($member['fid'] ?? 0),
                    'number' => (string) ($member['fnumber'] ?? ''),
                    'nickname' => (string) ($member['fname'] ?? ''),
                ] : null;
                unset($item['openid']);
                return $item;
            }, (array) ($payload['items'] ?? []));

            return $payload;
        });
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

    public function updateUserStatus(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            return $this->users->updateUserStatus(
                (int) ($params['id'] ?? 0),
                (string) $request->input('status', 'active')
            );
        });
    }

    public function resetUserPassword(Request $request, array $params = []): Response
    {
        return $this->adminRespond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $this->users->resetPasswordToDefault((int) ($params['id'] ?? 0));

            return ['message' => '密码已重置为手机号后 8 位。'];
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
            (int) $request->input('page_size', 15),
            (string) $request->input('keyword', '')
        ));
    }

    public function orderDetail(Request $request, array $params = []): Response
    {
        return $this->adminRespond(fn (): array => $this->orders->adminOrderDetail((int) ($params['id'] ?? 0)));
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
                'wechat_pay' => ['api_v3_key', 'private_key_content', 'public_key_content'],
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
            $admin = $this->users->requireAdmin();
            $logger = $this->app->make('logger');
            $file = $request->files['file'] ?? [];
            $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $logger->warning('admin_upload', '后台图片上传失败', [
                    'reason' => 'php_upload_error',
                    'upload_error' => $uploadError,
                    'source_name' => (string) ($file['name'] ?? ''),
                    'content_length' => (int) ($request->server['CONTENT_LENGTH'] ?? 0),
                ], (int) $admin['id'], $request);
                throw new \RuntimeException($this->uploadErrorMessage($uploadError));
            }

            if (empty($file['tmp_name'])) {
                $logger->warning('admin_upload', '后台图片上传失败', [
                    'reason' => 'missing_tmp_file',
                    'source_name' => (string) ($file['name'] ?? ''),
                    'content_length' => (int) ($request->server['CONTENT_LENGTH'] ?? 0),
                ], (int) $admin['id'], $request);
                throw new \RuntimeException('未接收到上传文件。');
            }

            $uploadDir = (string) $this->app->config()->get('app.upload_path', base_path('storage/uploads'));
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException('创建上传目录失败，请检查目录权限。');
            }

            $extension = pathinfo((string) $file['name'], PATHINFO_EXTENSION) ?: 'png';
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $extension = strtolower($extension);
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new \RuntimeException('仅支持上传常见图片格式。');
            }

            $filename = 'editor-' . date('YmdHis') . '-' . random_int(1000, 9999) . '.' . strtolower($extension);
            $destination = $uploadDir . '/' . $filename;
            if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                $logger->warning('admin_upload', '后台图片上传失败', [
                    'reason' => 'move_uploaded_file_failed',
                    'source_name' => (string) ($file['name'] ?? ''),
                    'target_name' => $filename,
                    'content_length' => (int) ($request->server['CONTENT_LENGTH'] ?? 0),
                ], (int) $admin['id'], $request);
                throw new \RuntimeException('保存上传文件失败，请检查目录权限。');
            }

            $logger->info('admin_upload', '后台图片上传成功', [
                'source_name' => (string) ($file['name'] ?? ''),
                'target_name' => $filename,
                'extension' => $extension,
                'size' => (int) ($file['size'] ?? 0),
                'location' => '/mall/uploads/' . $filename,
            ], (int) $admin['id'], $request);

            return ['location' => '/mall/uploads/' . $filename];
        });
    }

    private function uploadErrorMessage(int $uploadError): string
    {
        return match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '上传图片过大，请压缩后重试。',
            UPLOAD_ERR_PARTIAL => '图片上传未完成，请重试。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录，请联系管理员处理。',
            UPLOAD_ERR_CANT_WRITE => '服务器写入上传文件失败，请联系管理员处理。',
            UPLOAD_ERR_EXTENSION => '图片上传被服务器扩展拦截，请联系管理员处理。',
            default => '未接收到上传文件。',
        };
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
