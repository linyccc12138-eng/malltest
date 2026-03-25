<?php

declare(strict_types=1);

namespace Mall\Controllers;

use Mall\Core\Request;
use Mall\Core\Response;
use Mall\Services\UserService;
use Mall\Services\WechatService;

class OpenAccountApiController extends BaseController
{
    public function __construct(
        \Mall\Core\App $app,
        private readonly UserService $users,
        private readonly WechatService $wechat
    ) {
        parent::__construct($app);
    }

    public function login(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($request): array {
            $user = $this->users->authenticateWithPassword(
                trim((string) $request->input('phone', '')),
                (string) $request->input('password', ''),
                $request,
                false
            );

            return [
                'user' => $this->users->toPublicUser($user),
            ];
        });
    }

    public function wechatLogin(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($request): array {
            $code = trim((string) $request->input('code', ''));
            if ($code === '') {
                throw new \RuntimeException('缺少微信授权 code。');
            }

            $oauth = $this->wechat->exchangeOauthCode($code);
            $openid = trim((string) ($oauth['openid'] ?? ''));
            if ($openid === '') {
                throw new \RuntimeException('未能获取微信 OpenID。');
            }

            $user = $this->users->authenticateWithOpenid($openid, $request);

            return [
                'user' => $this->users->toPublicUser($user),
            ];
        });
    }

    public function authorizeUrl(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($request): array {
            $redirectUri = trim((string) $request->input('redirect_uri', ''));
            $state = trim((string) $request->input('state', ''));
            $scope = trim((string) $request->input('scope', '')) ?: 'snsapi_base';

            if ($redirectUri === '' || $state === '') {
                throw new \RuntimeException('缺少微信授权地址或 state。');
            }

            return [
                'authorize_url' => $this->wechat->buildOauthUrl($redirectUri, $state, $scope),
                'state' => $state,
            ];
        });
    }

    public function users(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($request): array {
            $ids = $this->parseIds($request->input('ids', []));

            return $this->users->searchUsers(
                $ids,
                trim((string) $request->input('keyword', '')),
                (int) $request->input('page', 1),
                (int) $request->input('page_size', $ids !== [] ? max(count($ids), 1) : 20)
            );
        });
    }

    public function userDetail(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($params): array {
            $user = $this->users->findUser((int) ($params['id'] ?? 0));
            if (!$user) {
                throw new \RuntimeException('用户不存在。');
            }

            return ['user' => $this->users->searchUsers([(int) $user['id']], '', 1, 1)['items'][0] ?? $user];
        });
    }

    public function createUser(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($request): array {
            return $this->users->createOrFindUser(
                array_merge($request->all(), ['role' => 'customer']),
                !empty($request->input('allow_existing', false))
            );
        });
    }

    public function resetPassword(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($params): array {
            $password = $this->users->resetPasswordToDefault((int) ($params['id'] ?? 0));

            return [
                'message' => '密码已重置为手机号后 8 位。',
                'default_password' => $password,
            ];
        });
    }

    public function changePassword(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($request, $params): array {
            $this->users->changePasswordForUser(
                (int) ($params['id'] ?? 0),
                (string) $request->input('current_password', ''),
                (string) $request->input('new_password', '')
            );

            return ['message' => '密码修改成功。'];
        });
    }

    public function bindWechat(Request $request, array $params = []): Response
    {
        return $this->respond($request, function () use ($request, $params): array {
            $code = trim((string) $request->input('code', ''));
            if ($code === '') {
                throw new \RuntimeException('缺少微信授权 code。');
            }

            $oauth = $this->wechat->exchangeOauthCode($code);
            $openid = trim((string) ($oauth['openid'] ?? ''));
            if ($openid === '') {
                throw new \RuntimeException('未能获取微信 OpenID。');
            }

            return [
                'message' => '微信绑定成功。',
                'user' => $this->users->toPublicUser(
                    $this->users->bindOpenid((int) ($params['id'] ?? 0), $openid, $request)
                ),
            ];
        });
    }

    private function respond(Request $request, callable $callback): Response
    {
        try {
            $this->assertAuthorized($request);
            return $this->json(['success' => true, 'data' => $callback()]);
        } catch (\Throwable $throwable) {
            $status = (int) $throwable->getCode();
            if ($status < 400 || $status > 599) {
                $status = 400;
            }

            return $this->json([
                'success' => false,
                'message' => $throwable->getMessage(),
            ], $status);
        }
    }

    private function assertAuthorized(Request $request): void
    {
        $configuredKey = trim((string) $this->app->config()->get('app.account_api_key', ''));
        $providedKey = trim((string) (
            $request->header('X-ACCOUNT-API-KEY', '')
            ?: ($request->server['HTTP_X_ACCOUNT_API_KEY'] ?? '')
            ?: ($request->server['REDIRECT_HTTP_X_ACCOUNT_API_KEY'] ?? '')
            ?: $request->input('api_key', '')
        ));

        if ($providedKey === '' && $request->rawBody !== '') {
            $decoded = json_decode($request->rawBody, true);
            if (is_array($decoded)) {
                $providedKey = trim((string) ($decoded['api_key'] ?? ''));
            }
        }

        if ($configuredKey === '' || !hash_equals($configuredKey, $providedKey)) {
            throw new \RuntimeException('开放接口认证失败。', 401);
        }
    }

    private function parseIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value), static fn (int $id): bool => $id > 0));
        }

        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', preg_split('/\s*,\s*/', $text) ?: []),
            static fn (int $id): bool => $id > 0
        ));
    }
}
