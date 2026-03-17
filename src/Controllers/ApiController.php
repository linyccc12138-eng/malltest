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
            $selectedIds = $request->input('selected_item_ids', []);
            $selectedIds = is_array($selectedIds) ? $selectedIds : [];

            return $this->orders->createOrderFromCart(
                (int) $user['id'],
                (int) $request->input('address_id', 0),
                $selectedIds
            );
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
        return $this->respond(function (): array {
            $user = $this->users->requireUser();
            $redirectUri = rtrim((string) $this->app->config()->get('app.url'), '/') . '/mall/api/wechat/callback';
            $state = 'bind_' . $user['id'] . '_' . bin2hex(random_bytes(4));

            return [
                'authorize_url' => $this->wechat->buildOauthUrl($redirectUri, $state),
            ];
        });
    }

    public function wechatCallback(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            $user = $this->users->requireUser();
            $code = (string) $request->input('code', '');
            if ($code === '') {
                throw new \RuntimeException('微信授权失败，缺少 code 参数。');
            }

            $oauth = $this->wechat->exchangeOauthCode($code);
            $openid = (string) ($oauth['openid'] ?? '');
            if ($openid === '') {
                throw new \RuntimeException('未能获取微信 OpenID。');
            }

            $freshUser = $this->users->findUser((int) $user['id']);
            if (!$freshUser) {
                throw new \RuntimeException('当前用户不存在。');
            }

            $this->users->updateUser((int) $user['id'], [
                'username' => $freshUser['username'],
                'nickname' => $freshUser['nickname'],
                'phone' => $freshUser['phone'],
                'role' => $freshUser['role'],
                'openid' => $openid,
                'membership_member_id' => $freshUser['membership_member_id'],
                'status' => $freshUser['status'],
            ]);

            return [
                'message' => '微信绑定成功。',
                'openid' => $openid,
            ];
        });
    }

    public function wechatNotify(Request $request, array $params = []): Response
    {
        return $this->respond(function () use ($request): array {
            return $this->orders->handleWechatNotify($request->server, $request->rawBody);
        });
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
