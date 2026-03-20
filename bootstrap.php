<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/src/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Mall\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$envPath = BASE_PATH . '/.env';
if (is_file($envPath)) {
    load_env($envPath);
}

$config = new Mall\Core\Config(BASE_PATH . '/config');
$app = new Mall\Core\App($config);

$session = new Mall\Core\SessionManager($config);
$session->start();

$db = new Mall\Core\DatabaseManager($config);
$view = new Mall\Core\View(BASE_PATH . '/views');
$redis = new Mall\Core\RedisClient($config);
$rateLimiter = new Mall\Core\RateLimiter(BASE_PATH . '/storage/cache/rate_limits.json', $redis);
$crypto = new Mall\Core\Crypto((string) $config->get('security.app_key'));
$logger = new Mall\Core\Logger($db, BASE_PATH . '/storage/logs', $config, $session);
$csrf = new Mall\Core\CsrfManager($session);
$sanitizer = new Mall\Core\HtmlSanitizer();

$settingsService = new Mall\Services\SettingsService($db, $crypto, $logger);
$membershipService = new Mall\Services\MembershipService($db, $logger, $settingsService);
$userService = new Mall\Services\UserService($db, $session, $logger, $rateLimiter);
$catalogService = new Mall\Services\CatalogService($db, $sanitizer);
$wechatService = new Mall\Services\WechatService($settingsService, $logger);
$notificationService = new Mall\Services\NotificationService($db, $wechatService, $settingsService, $logger);
$dashboardService = new Mall\Services\DashboardService($db);
$orderService = new Mall\Services\OrderService($db, $membershipService, $userService, $redis, $logger, $wechatService, $notificationService);

$app->instance('session', $session);
$app->instance('db', $db);
$app->instance('view', $view);
$app->instance('redis', $redis);
$app->instance('rate_limiter', $rateLimiter);
$app->instance('crypto', $crypto);
$app->instance('logger', $logger);
$app->instance('csrf', $csrf);
$app->instance('sanitizer', $sanitizer);
$app->instance('settings', $settingsService);
$app->instance('membership', $membershipService);
$app->instance('users', $userService);
$app->instance('catalog', $catalogService);
$app->instance('wechat', $wechatService);
$app->instance('notifications', $notificationService);
$app->instance('dashboard', $dashboardService);
$app->instance('orders', $orderService);

$pageController = new Mall\Controllers\PageController($app, $catalogService, $userService, $membershipService, $settingsService, $orderService);
$apiController = new Mall\Controllers\ApiController($app, $catalogService, $userService, $membershipService, $orderService, $wechatService);
$adminApiController = new Mall\Controllers\AdminApiController($app, $dashboardService, $catalogService, $userService, $membershipService, $orderService, $settingsService, $wechatService, $db);
$openAccountApiController = new Mall\Controllers\OpenAccountApiController($app, $userService, $wechatService);
$router = $app->router();

$router->get('/', [$pageController, 'navigation']);
$router->get('/portal', [$pageController, 'navigation']);
$router->get('/portal/activities/{id}', [$pageController, 'activityDetail']);
$router->get('/mall', [$pageController, 'mallHome']);
$router->get('/mall/activities/{id}', [$pageController, 'activityDetail']);
$router->get('/mall/login', [$pageController, 'login']);
$router->get('/mall/uploads/{filename}', [$pageController, 'uploadedFile']);
$router->get('/mall/products/{id}', [$pageController, 'productDetail']);
$router->get('/mall/profile', [$pageController, 'profile']);
$router->get('/mall/cart', [$pageController, 'cart']);
$router->get('/mall/checkout', [$pageController, 'checkout']);
$router->get('/mall/bind/wechat', [$pageController, 'bindWechat']);
$router->get('/mall/order-detail', [$pageController, 'orderDetail']);
$router->get('/mall/order-result', [$pageController, 'orderResult']);
$router->get('/mall/admin', [$pageController, 'admin']);
$router->get('/mall/admin/products/edit', [$pageController, 'adminProductEditor']);
$router->get('/mall/admin/activities/edit', [$pageController, 'adminActivityEditor']);

$router->get('/mall/api/session', [$apiController, 'session']);
$router->post('/mall/api/auth/login', [$apiController, 'login']);
$router->post('/mall/api/auth/wechat-login', [$apiController, 'wechatLogin']);
$router->post('/mall/api/auth/logout', [$apiController, 'logout']);
$router->get('/mall/api/products', [$apiController, 'products']);
$router->get('/mall/api/products/{id}/quick-view', [$apiController, 'quickView']);
$router->get('/mall/api/cart', [$apiController, 'cart']);
$router->post('/mall/api/cart', [$apiController, 'addCart']);
$router->put('/mall/api/cart/{id}', [$apiController, 'updateCart']);
$router->delete('/mall/api/cart/{id}', [$apiController, 'deleteCart']);
$router->get('/mall/api/profile', [$apiController, 'profile']);
$router->put('/mall/api/profile', [$apiController, 'updateProfile']);
$router->put('/mall/api/profile/password', [$apiController, 'changePassword']);
$router->get('/mall/api/addresses', [$apiController, 'addresses']);
$router->post('/mall/api/addresses', [$apiController, 'saveAddress']);
$router->put('/mall/api/addresses/{id}', [$apiController, 'saveAddress']);
$router->delete('/mall/api/addresses/{id}', [$apiController, 'deleteAddress']);
$router->get('/mall/api/orders', [$apiController, 'orders']);
$router->post('/mall/api/orders', [$apiController, 'createOrder']);
$router->get('/mall/api/orders/{id}', [$apiController, 'orderDetail']);
$router->get('/mall/api/order-detail-access', [$apiController, 'orderDetailAccess']);
$router->post('/mall/api/orders/{id}/pay/balance', [$apiController, 'payBalance']);
$router->post('/mall/api/orders/{id}/pay/wechat', [$apiController, 'payWechat']);
$router->get('/mall/api/orders/{id}/pay/wechat/status', [$apiController, 'wechatPayStatus']);
$router->post('/mall/api/orders/{id}/cancel', [$apiController, 'cancelOrder']);
$router->post('/mall/api/orders/{id}/complete', [$apiController, 'completeOrder']);
$router->get('/mall/api/wallet', [$apiController, 'wallet']);
$router->get('/mall/api/wechat/bind-url', [$apiController, 'bindWechatUrl']);
$router->get('/mall/api/wechat/oauth-url', [$apiController, 'wechatOauthUrl']);
$router->get('/mall/api/wechat/status', [$apiController, 'wechatStatus']);
$router->post('/mall/api/wechat/bind', [$apiController, 'bindWechatCurrent']);
$router->post('/mall/api/wechat/unbind', [$apiController, 'unbindWechatCurrent']);
$router->get('/mall/api/wechat/jssdk-config', [$apiController, 'wechatJsSdkConfig']);
$router->get('/mall/api/wechat/callback', [$apiController, 'wechatCallback']);
$router->post('/mall/api/wechat/notify', [$apiController, 'wechatNotify']);

$router->get('/mall/api/open/accounts', [$openAccountApiController, 'users']);
$router->post('/mall/api/open/accounts/search', [$openAccountApiController, 'users']);
$router->get('/mall/api/open/accounts/{id}', [$openAccountApiController, 'userDetail']);
$router->post('/mall/api/open/accounts', [$openAccountApiController, 'createUser']);
$router->post('/mall/api/open/accounts/login', [$openAccountApiController, 'login']);
$router->post('/mall/api/open/accounts/wechat-login', [$openAccountApiController, 'wechatLogin']);
$router->post('/mall/api/open/accounts/{id}/bind-wechat', [$openAccountApiController, 'bindWechat']);
$router->post('/mall/api/open/accounts/{id}/reset-password', [$openAccountApiController, 'resetPassword']);
$router->post('/mall/api/open/accounts/{id}/change-password', [$openAccountApiController, 'changePassword']);
$router->post('/mall/api/open/accounts/{id}', [$openAccountApiController, 'userDetail']);
$router->get('/mall/api/open/wechat/authorize-url', [$openAccountApiController, 'authorizeUrl']);
$router->post('/mall/api/open/wechat/authorize-url', [$openAccountApiController, 'authorizeUrl']);

$router->get('/mall/api/admin/dashboard', [$adminApiController, 'dashboard']);
$router->get('/mall/api/admin/products', [$adminApiController, 'products']);
$router->post('/mall/api/admin/products', [$adminApiController, 'saveProduct']);
$router->put('/mall/api/admin/products/{id}', [$adminApiController, 'saveProduct']);
$router->post('/mall/api/admin/products/batch', [$adminApiController, 'batchProducts']);
$router->get('/mall/api/admin/categories', [$adminApiController, 'categories']);
$router->post('/mall/api/admin/categories', [$adminApiController, 'saveCategory']);
$router->put('/mall/api/admin/categories/{id}', [$adminApiController, 'saveCategory']);
$router->post('/mall/api/admin/categories/sort', [$adminApiController, 'sortCategories']);
$router->get('/mall/api/admin/activities', [$adminApiController, 'activities']);
$router->post('/mall/api/admin/activities', [$adminApiController, 'saveActivity']);
$router->put('/mall/api/admin/activities/{id}', [$adminApiController, 'saveActivity']);
$router->get('/mall/api/admin/users', [$adminApiController, 'users']);
$router->post('/mall/api/admin/users', [$adminApiController, 'saveUser']);
$router->put('/mall/api/admin/users/{id}', [$adminApiController, 'saveUser']);
$router->post('/mall/api/admin/users/{id}/status', [$adminApiController, 'updateUserStatus']);
$router->post('/mall/api/admin/users/{id}/reset-password', [$adminApiController, 'resetUserPassword']);
$router->get('/mall/api/admin/members', [$adminApiController, 'members']);
$router->get('/mall/api/admin/member-classes', [$adminApiController, 'memberClasses']);
$router->post('/mall/api/admin/members', [$adminApiController, 'saveMember']);
$router->put('/mall/api/admin/members/{id}', [$adminApiController, 'saveMember']);
$router->post('/mall/api/admin/members/{id}/balance', [$adminApiController, 'adjustMemberBalance']);
$router->get('/mall/api/admin/orders', [$adminApiController, 'orders']);
$router->get('/mall/api/admin/orders/{id}', [$adminApiController, 'orderDetail']);
$router->post('/mall/api/admin/orders/{id}/ship', [$adminApiController, 'shipOrder']);
$router->post('/mall/api/admin/orders/{id}/close', [$adminApiController, 'closeOrder']);
$router->get('/mall/api/admin/settings', [$adminApiController, 'settings']);
$router->post('/mall/api/admin/settings/test-membership', [$adminApiController, 'testMembership']);
$router->post('/mall/api/admin/settings/test-wechat-pay', [$adminApiController, 'testWechatPay']);
$router->post('/mall/api/admin/settings/test-service-account', [$adminApiController, 'testServiceAccount']);
$router->post('/mall/api/admin/settings/{group}', [$adminApiController, 'saveSettings']);
$router->get('/mall/api/admin/logs', [$adminApiController, 'logs']);
$router->post('/mall/api/admin/upload', [$adminApiController, 'upload']);

return $app;
