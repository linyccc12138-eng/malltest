<?php

declare(strict_types=1);

namespace Mall\Controllers;

use Mall\Core\Request;
use Mall\Core\Response;
use Mall\Services\CatalogService;
use Mall\Services\MembershipService;
use Mall\Services\OrderService;
use Mall\Services\UserService;
use Mall\Services\WechatService;

class ApiController extends BaseController
{
    public function __construct(
        \Mall\Core\App $app,
        private readonly CatalogService $catalog,
        private readonly UserService $users,
        private readonly MembershipService $membership,
        private readonly OrderService $orders,
        private readonly WechatService $wechat
    ) {
        parent::__construct($app);
    }

    public function session(Request $request, array $params = []): Response
    {
        return $this->respond(function (): array {
            $user = $this->users->currentUser();

            return [
                'user' => $user,
                'member' => $user ? $this->membership->getMallUserMember((int) $user['id']) : null,
                'csrf_token' => $this->app->make('csrf')->token(),
            ];
        });
    }

    public function login(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $result = $this->users->login(
                trim((string) $request->input('username', '')),
                (string) $request->input('password', ''),
                $request
            );

            return ['user' => $result];
        });
    }

    public function wechatLogin(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);

            $oauthOpenid = $this->currentOauthOpenid();
            if ($oauthOpenid === '') {
                if (!$this->isWechatClient($request)) {
                    throw new \RuntimeException('请在微信客户端中打开。');
                }

                throw new \RuntimeException('尚未获取当前微信身份，请稍后重试。');
            }

            return ['user' => $this->users->loginWithOpenid($oauthOpenid, $request)];
        });
    }

    public function logout(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $this->users->logout();

            return ['message' => '已退出登录。'];
        });
    }

    public function products(Request $request, array $params = []): Response
    {
        return $this->respond(fn (): array => $this->catalog->searchProducts($request->all()));
    }

    public function quickView(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($params): array {
            $product = $this->catalog->quickView((int) ($params['id'] ?? 0));
            if (!$product) {
                throw new \RuntimeException('商品不存在。');
            }

            return $product;
        });
    }

    public function cart(Request $request, array $params = []): Response
    {
        return $this->respond(function (): array {
            $user = $this->users->requireUser();
            return $this->orders->cart((int) $user['id']);
        });
    }

    public function addCart(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();

            return $this->orders->addToCart(
                (int) $user['id'],
                (int) $request->input('product_id', 0),
                (int) $request->input('sku_id', 0),
                (int) $request->input('quantity', 1)
            );
        });
    }

    public function updateCart(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();

            return $this->orders->updateCartItem(
                (int) $user['id'],
                (int) ($params['id'] ?? 0),
                (int) $request->input('quantity', 1)
            );
        });
    }

    public function deleteCart(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            $this->orders->removeCartItem((int) $user['id'], (int) ($params['id'] ?? 0));

            return ['message' => '购物车商品已移除。'];
        });
    }

    public function profile(Request $request, array $params = []): Response
    {
        return $this->respond(function (): array {
            $user = $this->users->requireUser();

            return [
                'user' => $this->users->findUser((int) $user['id']),
                'member' => $this->membership->getMallUserMember((int) $user['id']),
            ];
        });
    }

    public function updateProfile(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            return $this->users->updateProfile((int) $user['id'], $request->all());
        });
    }

    public function changePassword(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            $this->users->changePassword(
                (int) $user['id'],
                (string) $request->input('current_password', ''),
                (string) $request->input('new_password', '')
            );

            return ['message' => '密码修改成功。'];
        });
    }

    public function addresses(Request $request, array $params = []): Response
    {
        return $this->respond(function (): array {
            $user = $this->users->requireUser();
            return $this->users->addresses((int) $user['id']);
        });
    }

    public function saveAddress(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            $addressId = isset($params['id']) ? (int) $params['id'] : null;

            return $this->users->saveAddress((int) $user['id'], $request->all(), $addressId);
        });
    }

    public function deleteAddress(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            $this->users->deleteAddress((int) $user['id'], (int) ($params['id'] ?? 0));

            return ['message' => '地址已删除。'];
        });
    }

    public function orders(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $user = $this->users->requireUser();
            return $this->orders->userOrders(
                (int) $user['id'],
                (string) $request->input('group', 'all'),
                (int) $request->input('page', 1),
                (int) $request->input('page_size', 15)
            );
        });
    }

    public function createOrder(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            $mode = (string) $request->input('mode', 'cart');
            $selectedIds = $request->input('selected_item_ids', []);
            $selectedIds = is_array($selectedIds) ? $selectedIds : [];

            if ($mode === 'buy_now') {
                return $this->orders->createOrderFromBuyNow(
                    (int) $user['id'],
                    (int) $request->input('address_id', 0),
                    (int) $request->input('product_id', 0),
                    (int) $request->input('sku_id', 0),
                    (int) $request->input('quantity', 1)
                );
            }

            return $this->orders->createOrderFromCart((int) $user['id'], (int) $request->input('address_id', 0), $selectedIds);
        });
    }

    public function orderDetail(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($params): array {
            $user = $this->users->requireUser();
            $order = $this->orders->findOrder((int) ($params['id'] ?? 0), (int) $user['id'], true);
            if (!$order) {
                throw new \RuntimeException('订单不存在。');
            }

            return $order;
        });
    }

    public function orderDetailAccess(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            return $this->orders->orderDetailByAccessToken((string) $request->input('token', ''));
        });
    }

    public function payBalance(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            return $this->orders->payWithBalance((int) $user['id'], (int) ($params['id'] ?? 0));
        });
    }

    public function payWechat(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            return $this->orders->startWechatPay((int) $user['id'], (int) ($params['id'] ?? 0));
        });
    }

    public function cancelOrder(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            return $this->orders->cancelOrder((int) $user['id'], (int) ($params['id'] ?? 0));
        });
    }

    public function completeOrder(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request, $params): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            return $this->orders->completeOrder((int) $user['id'], (int) ($params['id'] ?? 0));
        });
    }

    public function wallet(Request $request, array $params = []): Response
    {
        return $this->respond(function (): array {
            $user = $this->users->requireUser();

            return [
                'member' => $this->membership->getMallUserMember((int) $user['id']),
                'records' => $this->orders->walletRecords((int) $user['id']),
            ];
        });
    }

    public function bindWechatUrl(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            return $this->buildWechatOauthUrlPayload($request, 'bind', '/mall/bind/wechat');
        });
    }

    public function wechatOauthUrl(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $scene = (string) $request->input('scene', 'login');
            $defaultReturnUrl = $scene === 'bind' ? '/mall/bind/wechat' : '/mall/login';

            return $this->buildWechatOauthUrlPayload($request, $scene, $defaultReturnUrl);
        });
    }

    public function wechatStatus(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $user = $this->users->currentUser();
            $oauthOpenid = $this->currentOauthOpenid();
            $oauthOwner = $oauthOpenid !== '' ? $this->users->findUserByOpenid($oauthOpenid) : null;
            $userOpenid = trim((string) ($user['openid'] ?? ''));
            $currentMatches = $oauthOpenid !== '' && $userOpenid !== '' && hash_equals($userOpenid, $oauthOpenid);

            return [
                'is_wechat_client' => $this->isWechatClient($request),
                'oauth_openid_ready' => $oauthOpenid !== '',
                'oauth_openid_masked' => $this->maskOpenid($oauthOpenid),
                'user_has_openid' => $userOpenid !== '',
                'user_openid_masked' => $this->maskOpenid($userOpenid),
                'current_wechat_matches_user' => $currentMatches,
                'oauth_openid_bound_to_other_user' => (bool) ($oauthOwner && (!$user || (int) $oauthOwner['id'] !== (int) $user['id'])),
                'can_bind_current_wechat' => (bool) ($user && $userOpenid === '' && $oauthOpenid !== '' && (!$oauthOwner || (int) $oauthOwner['id'] === (int) $user['id'])),
                'can_unbind_current_wechat' => (bool) ($user && $userOpenid !== ''),
            ];
        });
    }

    public function bindWechatCurrent(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();
            $oauthOpenid = $this->currentOauthOpenid();
            if ($oauthOpenid === '') {
                if (!$this->isWechatClient($request)) {
                    throw new \RuntimeException('请在微信客户端中打开。');
                }

                throw new \RuntimeException('获取微信授权失败，请尝试重新进入。');
            }

            return [
                'message' => '微信绑定成功。',
                'user' => $this->users->bindOpenid((int) $user['id'], $oauthOpenid, $request),
            ];
        });
    }

    public function unbindWechatCurrent(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $this->validateCsrf($request);
            $user = $this->users->requireUser();

            return [
                'message' => '微信解绑成功。',
                'user' => $this->users->unbindOpenid((int) $user['id'], $request),
            ];
        });
    }

    public function wechatJsSdkConfig(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $url = trim((string) $request->input('url', ''));
            if ($url === '') {
                throw new \RuntimeException('缺少签名页面地址。');
            }

            $currentHost = (string) parse_url((string) $this->app->config()->get('app.url'), PHP_URL_HOST);
            $requestHost = (string) parse_url($url, PHP_URL_HOST);
            if ($currentHost !== '' && $requestHost !== '' && strcasecmp($currentHost, $requestHost) !== 0) {
                throw new \RuntimeException('签名域名与当前站点不一致。');
            }

            return $this->wechat->buildJsSdkConfig($url, [
                'updateAppMessageShareData',
                'updateTimelineShareData',
                'onMenuShareAppMessage',
                'onMenuShareTimeline',
                'showOptionMenu',
            ]);
        });
    }

    public function wechatCallback(Request $request, array $params = []): Response
    {
        $state = trim((string) $request->input('state', ''));
        $pending = $this->consumeWechatOauthPending($state);
        $scene = (string) ($pending['scene'] ?? 'login');
        $returnUrl = $this->normalizeReturnUrl(
            (string) ($pending['return_url'] ?? ($scene === 'bind' ? '/mall/bind/wechat' : '/mall/login')),
            $scene === 'bind' ? '/mall/bind/wechat' : '/mall/login'
        );

        if ($state === '' || !$pending) {
            $this->logger()->warning('wechat_auth', '微信 OAuth 回调状态校验失败', [
                'state' => $state,
                'request_path' => $request->path,
            ], null, $request);
            $this->sessionStore()->flash('error', '微信授权状态已失效，请重新进入页面。');
            return $this->redirect('/mall/login');
        }

        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $this->logger()->warning('wechat_auth', '微信 OAuth 回调缺少 code', [
                'scene' => $scene,
                'return_url' => $returnUrl,
                'pending_user_id' => $pending['user_id'] ?? null,
            ], null, $request);
            $this->sessionStore()->flash('error', '获取微信授权失败，请尝试重新进入。');
            return $this->redirect($returnUrl);
        }

        try {
            $oauth = $this->wechat->exchangeOauthCode($code);
            $openid = trim((string) ($oauth['openid'] ?? ''));
            if ($openid === '') {
                throw new \RuntimeException('未能获取微信 OpenID。');
            }

            $this->storeOauthOpenid($openid, $scene, $returnUrl);
            $this->logger()->info('wechat_auth', '微信 OAuth 回调成功', [
                'scene' => $scene,
                'return_url' => $returnUrl,
                'pending_user_id' => $pending['user_id'] ?? null,
                'openid_masked' => $this->maskOpenid($openid),
                'openid_hash' => sha1($openid),
            ], null, $request);

            $this->sessionStore()->flash('success', $scene === 'bind'
                ? '已识别当前微信，请确认是否绑定。'
                : '已识别当前微信，可直接使用微信登录。');

            return $this->redirect($returnUrl);
        } catch (\Throwable $throwable) {
            $this->logger()->error('wechat_auth', '微信 OAuth 回调失败', [
                'scene' => $scene,
                'return_url' => $returnUrl,
                'pending_user_id' => $pending['user_id'] ?? null,
                'error' => $throwable->getMessage(),
            ], null, $request);
            $this->sessionStore()->flash('error', '获取微信授权失败，请尝试重新进入。');
            return $this->redirect($returnUrl);
        }
    }

    public function wechatNotify(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            return $this->orders->handleWechatNotify($request->server, $request->rawBody);
        });
    }

    private function buildWechatOauthUrlPayload(Request $request, string $scene, string $defaultReturnUrl): array
    {
        $normalizedScene = in_array($scene, ['login', 'bind'], true) ? $scene : '';
        if ($normalizedScene === '') {
            throw new \RuntimeException('不支持的微信授权场景。');
        }

        $user = $normalizedScene === 'bind' ? $this->users->requireUser() : $this->users->currentUser();
        $returnUrl = $this->normalizeReturnUrl((string) $request->input('return_url', ''), $defaultReturnUrl);
        $redirectUri = rtrim((string) $this->app->config()->get('app.url'), '/') . '/mall/api/wechat/callback';
        $state = sprintf('wechat_%s_%s', $normalizedScene, bin2hex(random_bytes(8)));

        $pending = $this->pruneWechatOauthPendings((array) $this->sessionStore()->get('wechat_oauth_pending', []));
        $pending[$state] = [
            'scene' => $normalizedScene,
            'return_url' => $returnUrl,
            'user_id' => $user ? (int) $user['id'] : null,
            'created_at' => time(),
        ];
        $this->sessionStore()->put('wechat_oauth_pending', $pending);

        $this->logger()->info('wechat_auth', '发起微信 OAuth 授权', [
            'scene' => $normalizedScene,
            'return_url' => $returnUrl,
            'state' => $state,
            'is_wechat_client' => $this->isWechatClient($request),
            'current_user_id' => $user ? (int) $user['id'] : null,
        ], $user ? (int) $user['id'] : null, $request);

        return [
            'authorize_url' => $this->wechat->buildOauthUrl($redirectUri, $state),
            'state' => $state,
        ];
    }

    private function normalizeReturnUrl(string $url, string $default): string
    {
        $candidate = trim($url);
        if ($candidate === '') {
            return $default;
        }

        if (str_starts_with($candidate, '/')) {
            return $candidate;
        }

        $host = (string) parse_url((string) $this->app->config()->get('app.url'), PHP_URL_HOST);
        $candidateHost = (string) parse_url($candidate, PHP_URL_HOST);
        $candidatePath = (string) parse_url($candidate, PHP_URL_PATH);
        $candidateQuery = (string) parse_url($candidate, PHP_URL_QUERY);
        if ($host !== '' && $candidateHost !== '' && strcasecmp($host, $candidateHost) === 0 && $candidatePath !== '') {
            return $candidatePath . ($candidateQuery !== '' ? '?' . $candidateQuery : '');
        }

        return $default;
    }

    private function isWechatClient(Request $request): bool
    {
        return stripos($request->userAgent(), 'MicroMessenger') !== false;
    }

    private function currentOauthOpenid(): string
    {
        return trim((string) $this->sessionStore()->get('wechat_oauth_openid', ''));
    }

    private function storeOauthOpenid(string $openid, string $scene, string $returnUrl): void
    {
        $this->sessionStore()->put('wechat_oauth_openid', $openid);
        $this->sessionStore()->put('wechat_oauth_meta', [
            'scene' => $scene,
            'return_url' => $returnUrl,
            'obtained_at' => now(),
        ]);
    }

    private function consumeWechatOauthPending(string $state): ?array
    {
        $pending = $this->pruneWechatOauthPendings((array) $this->sessionStore()->get('wechat_oauth_pending', []));
        $payload = $pending[$state] ?? null;
        unset($pending[$state]);
        $this->sessionStore()->put('wechat_oauth_pending', $pending);

        return is_array($payload) ? $payload : null;
    }

    private function pruneWechatOauthPendings(array $pending): array
    {
        $now = time();
        $valid = [];
        foreach ($pending as $state => $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $createdAt = (int) ($payload['created_at'] ?? 0);
            if ($createdAt > 0 && ($now - $createdAt) <= 900) {
                $valid[$state] = $payload;
            }
        }

        return $valid;
    }

    private function maskOpenid(string $openid): string
    {
        if ($openid === '') {
            return '';
        }

        if (strlen($openid) <= 8) {
            return substr($openid, 0, 2) . '***';
        }

        return substr($openid, 0, 4) . '***' . substr($openid, -4);
    }

    private function logger(): \Mall\Core\Logger
    {
        return $this->app->make('logger');
    }

    private function sessionStore(): \Mall\Core\SessionManager
    {
        return $this->app->make('session');
    }

    private function respond(callable $callback): Response
    {
        try {
            return $this->json(['success' => true, 'data' => $callback()]);
        } catch (\Throwable $throwable) {
            return $this->json([
                'success' => false,
                'message' => $throwable->getMessage(),
            ], 400);
        }
    }
}
