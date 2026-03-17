<?php

declare(strict_types=1);

namespace Mall\Controllers;

use Mall\Core\Request;
use Mall\Core\Response;
use Mall\Services\CatalogService;
use Mall\Services\MembershipService;
use Mall\Services\OrderService;
use Mall\Services\SettingsService;
use Mall\Services\UserService;

class PageController extends BaseController
{
    public function __construct(
        \Mall\Core\App $app,
        private readonly CatalogService $catalog,
        private readonly UserService $users,
        private readonly MembershipService $membership,
        private readonly SettingsService $settings,
        private readonly OrderService $orders
    ) {
        parent::__construct($app);
    }

    public function navigation(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();

        return $this->view('pages/navigation', [
            'pageTitle' => '导航页',
            'pageKey' => 'navigation',
            'currentUser' => $user,
            'navData' => $this->catalog->navigationData(),
        ]);
    }

    public function mallHome(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        $member = $user ? $this->membership->getMallUserMember((int) $user['id']) : null;

        return $this->view('pages/mall-home', [
            'pageTitle' => '奇妙集市',
            'pageKey' => 'mallHome',
            'currentUser' => $user,
            'currentMember' => $member,
            'homeSections' => $this->catalog->homeSections(),
        ]);
    }

    public function login(Request $request, array $params = []): Response
    {
        if ($this->users->currentUser()) {
            return $this->redirect('/mall');
        }

        return $this->view('pages/login', [
            'pageTitle' => '登录',
            'pageKey' => 'login',
            'currentUser' => null,
        ]);
    }

    public function uploadedFile(Request $request, array $params = []): Response
    {
        $filename = basename((string) ($params['filename'] ?? ''));
        if ($filename === '') {
            return Response::html('<h1>404</h1><p>文件不存在。</p>', 404);
        }

        $uploadDir = (string) $this->app->config()->get('app.upload_path', base_path('storage/uploads'));
        $filePath = rtrim($uploadDir, '/') . '/' . $filename;
        if (!is_file($filePath)) {
            return Response::html('<h1>404</h1><p>文件不存在。</p>', 404);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return Response::html('<h1>500</h1><p>文件读取失败。</p>', 500);
        }

        return new Response($content, 200, [
            'Content-Type' => mime_content_type($filePath) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function productDetail(Request $request, array $params = []): Response
    {
        $product = $this->catalog->findProductBySlug((string) ($params['slug'] ?? ''));
        if (!$product) {
            return Response::html('<h1>404</h1><p>商品不存在或已下架。</p>', 404);
        }

        $user = $this->users->currentUser();
        $member = $user ? $this->membership->getMallUserMember((int) $user['id']) : null;

        return $this->view('pages/product-detail', [
            'pageTitle' => $product['name'],
            'pageKey' => 'productDetail',
            'currentUser' => $user,
            'currentMember' => $member,
            'product' => $product,
        ]);
    }

    public function profile(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user) {
            return $this->redirect('/mall/login');
        }

        return $this->view('pages/profile', [
            'pageTitle' => '用户中心',
            'pageKey' => 'profile',
            'currentUser' => $user,
            'currentMember' => $this->membership->getMallUserMember((int) $user['id']),
            'defaultAddress' => $this->users->defaultAddress((int) $user['id']),
        ]);
    }

    public function cart(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user) {
            return $this->redirect('/mall/login');
        }

        return $this->view('pages/cart', [
            'pageTitle' => '购物车',
            'pageKey' => 'cart',
            'currentUser' => $user,
            'currentMember' => $this->membership->getMallUserMember((int) $user['id']),
        ]);
    }

    public function checkout(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user) {
            return $this->redirect('/mall/login');
        }

        return $this->view('pages/checkout', [
            'pageTitle' => '确认订单',
            'pageKey' => 'checkout',
            'currentUser' => $user,
            'currentMember' => $this->membership->getMallUserMember((int) $user['id']),
            'addresses' => $this->users->addresses((int) $user['id']),
            'cart' => $this->orders->cart((int) $user['id']),
        ]);
    }

    public function bindWechat(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user) {
            return $this->redirect('/mall/login');
        }

        return $this->view('pages/bind-wechat', [
            'pageTitle' => '绑定微信',
            'pageKey' => 'bindWechat',
            'currentUser' => $user,
            'wechatConfig' => $this->settings->getGroup('wechat_service_account'),
        ]);
    }

    public function orderResult(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user) {
            return $this->redirect('/mall/login');
        }

        return $this->view('pages/order-result', [
            'pageTitle' => '订单结果',
            'pageKey' => 'orderResult',
            'currentUser' => $user,
            'orderId' => (int) $request->input('order_id', 0),
        ]);
    }

    public function admin(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->redirect('/mall/login');
        }

        return $this->view('pages/admin', [
            'pageTitle' => '管理后台',
            'pageKey' => 'admin',
            'currentUser' => $user,
            'settingsData' => $this->settings->allForAdmin(),
        ]);
    }

    public function adminProductEditor(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->redirect('/mall/login');
        }

        $productId = (int) $request->input('id', 0);
        $product = $productId > 0 ? $this->catalog->findProductById($productId) : null;
        if ($productId > 0 && !$product) {
            return Response::html('<h1>404</h1><p>商品不存在。</p>', 404);
        }

        return $this->view('pages/admin-product-editor', [
            'pageTitle' => $product ? '编辑商品' : '新建商品',
            'pageKey' => 'adminProductEditor',
            'currentUser' => $user,
            'product' => $product,
            'categories' => $this->catalog->categoriesTree(),
        ]);
    }

    public function adminActivityEditor(Request $request, array $params = []): Response
    {
        $user = $this->users->currentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->redirect('/mall/login');
        }

        $activityId = (int) $request->input('id', 0);
        $activity = $activityId > 0 ? $this->catalog->findActivityById($activityId) : null;
        if ($activityId > 0 && !$activity) {
            return Response::html('<h1>404</h1><p>活动不存在。</p>', 404);
        }

        return $this->view('pages/admin-activity-editor', [
            'pageTitle' => $activity ? '编辑活动' : '新建活动',
            'pageKey' => 'adminActivityEditor',
            'currentUser' => $user,
            'activity' => $activity,
        ]);
    }
}
