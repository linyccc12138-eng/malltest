<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\ApiException;
use Mall\Core\ConfirmRequiredException;
use Mall\Core\DatabaseManager;
use Mall\Core\Logger;
use Mall\Core\RateLimiter;
use Mall\Core\Request;
use Mall\Core\SessionManager;
use PDO;

class UserService
{
    private const LOGIN_FAILED_MESSAGE = '手机号或密码错误，或账号已停用。';
    private const LOGIN_LOCKED_MESSAGE = '登录失败过多，账号/IP已锁定，请联系管理员解锁。';

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SessionManager $session,
        private readonly Logger $logger,
        private readonly RateLimiter $rateLimiter,
        private readonly SettingsService $settings,
        private readonly CaptchaService $captcha
    ) {
    }

    public function currentUser(): ?array
    {
        $userId = (int) ($this->session->get('auth_user_id') ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, role, openid, membership_member_id, status, last_login_at, created_at
             FROM mall_users
             WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || ($user['status'] ?? 'disabled') !== 'active') {
            $this->session->forget('auth_user_id');
            return null;
        }

        return $user ?: null;
    }

    public function isAdmin(): bool
    {
        $user = $this->currentUser();
        return (bool) ($user && $user['role'] === 'admin');
    }

    public function login(string $identifier, string $password, Request $request): array
    {
        $user = $this->authenticateWithPassword($identifier, $password, $request, true, true);
        $this->session->put('auth_user_id', (int) $user['id']);
        $this->touchLastLogin((int) $user['id']);
        $this->logger->info('auth', '用户登录成功', [
            'identifier' => trim($identifier),
            'phone' => (string) ($user['phone'] ?? ''),
        ], (int) $user['id'], $request);

        return $this->publicUserPayload($user);
    }

    public function loginWithOpenid(string $openid, Request $request): array
    {
        $normalizedOpenid = $this->normalizeOpenid($openid);
        $user = $this->authenticateWithOpenid($normalizedOpenid, $request);
        $this->assertNoActiveLoginLocks($request->ip(), $user, $request);
        $this->clearLoginLockoutState($request->ip(), $user, $request, 'wechat_login_success');
        $this->session->put('auth_user_id', (int) $user['id']);
        $this->touchLastLogin((int) $user['id']);

        $this->logger->info(
            'wechat_auth',
            '微信登录成功',
            $this->openidLogContext($normalizedOpenid) + [
                'login_user_id' => (int) $user['id'],
                'login_phone' => (string) ($user['phone'] ?? ''),
            ],
            (int) $user['id'],
            $request
        );

        return $this->publicUserPayload($user);
    }

    public function logout(): void
    {
        $this->session->forget('auth_user_id');
    }

    public function requireUser(): array
    {
        $user = $this->currentUser();
        if (!$user) {
            throw new \RuntimeException('请先登录后再操作。');
        }

        return $user;
    }

    public function requireAdmin(): array
    {
        $user = $this->requireUser();
        if (($user['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('仅管理员可执行此操作。');
        }

        return $user;
    }

    public function updateProfile(int $userId, array $data): array
    {
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($nickname === '') {
            throw new \RuntimeException('昵称不能为空。');
        }

        $this->assertPhoneUnique($phone, $userId);

        $stmt = $this->db->mall()->prepare(
            'UPDATE mall_users SET username = :username, nickname = :nickname, phone = :phone, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':username' => $this->usernameFromPhone($phone),
            ':nickname' => $nickname,
            ':phone' => $phone,
            ':updated_at' => now(),
            ':id' => $userId,
        ]);

        return $this->findUser($userId) ?? [];
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $this->changePasswordForUser($userId, $currentPassword, $newPassword);
    }

    public function changePasswordForUser(int $userId, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new \RuntimeException('新密码至少需要 8 位。');
        }

        $stmt = $this->db->mall()->prepare('SELECT password_hash FROM mall_users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
            throw new \RuntimeException('当前密码不正确。');
        }

        $update = $this->db->mall()->prepare(
            'UPDATE mall_users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
        );
        $update->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':updated_at' => now(),
            ':id' => $userId,
        ]);
    }

    public function listUsers(int $page = 1, int $pageSize = 15): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $countStmt = $this->db->mall()->query('SELECT COUNT(*) AS total FROM mall_users');
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, role,
                    CASE WHEN openid IS NOT NULL AND openid <> \'\' THEN 1 ELSE 0 END AS wechat_bound,
                    membership_member_id, status, last_login_at, created_at
             FROM mall_users
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => array_map(fn (array $item): array => $this->publicUserPayload($item), $stmt->fetchAll()),
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ];
    }

    public function createUser(array $data): array
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $membershipMemberId = !empty($data['membership_member_id']) ? (int) $data['membership_member_id'] : null;
        $allowDuplicateMembership = !empty($data['allow_duplicate_membership']);
        if ($phone === '') {
            throw new \RuntimeException('手机号不能为空。');
        }

        $username = $this->usernameFromPhone($phone);
        $password = $this->defaultPasswordFromPhone($phone);
        $this->assertPhoneUnique($phone, null);
        $this->assertMembershipBindingAvailable($membershipMemberId, null, $allowDuplicateMembership);
        $this->assertOpenidAvailable($data['openid'] ?? null, null);

        $stmt = $this->db->mall()->prepare(
            'INSERT INTO mall_users (username, password_hash, nickname, phone, role, openid, membership_member_id, status, created_at, updated_at)
             VALUES (:username, :password_hash, :nickname, :phone, :role, :openid, :membership_member_id, :status, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':nickname' => trim((string) ($data['nickname'] ?? '')) ?: $phone,
            ':phone' => $phone,
            ':role' => (string) ($data['role'] ?? 'customer'),
            ':openid' => $this->normalizeOpenid($data['openid'] ?? null),
            ':membership_member_id' => $membershipMemberId,
            ':status' => (string) ($data['status'] ?? 'active'),
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return $this->findUser((int) $this->db->mall()->lastInsertId()) ?? [];
    }

    public function createOrFindUser(array $data, bool $allowExisting = false): array
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone === '') {
            throw new \RuntimeException('手机号不能为空。');
        }

        $existing = $this->findByPhone($phone);
        if ($existing) {
            if (!$allowExisting) {
                throw new \RuntimeException('手机号已存在。');
            }

            return [
                'created' => false,
                'default_password' => null,
                'user' => $this->publicUserPayload($this->findUser((int) $existing['id']) ?? $existing),
            ];
        }

        $user = $this->createUser($data);

        return [
            'created' => true,
            'default_password' => $this->defaultPasswordFromPhone($phone),
            'user' => $this->publicUserPayload($user),
        ];
    }

    public function updateUser(int $userId, array $data): array
    {
        $existing = $this->findUser($userId);
        if (!$existing) {
            throw new \RuntimeException('用户不存在。');
        }

        $phone = trim((string) ($data['phone'] ?? $existing['phone'] ?? ''));
        if ($phone === '') {
            throw new \RuntimeException('手机号不能为空。');
        }

        $username = $this->usernameFromPhone($phone);
        $membershipMemberId = array_key_exists('membership_member_id', $data)
            ? (!empty($data['membership_member_id']) ? (int) $data['membership_member_id'] : null)
            : ($existing['membership_member_id'] !== null ? (int) $existing['membership_member_id'] : null);
        $allowDuplicateMembership = !empty($data['allow_duplicate_membership']);

        $this->assertPhoneUnique($phone, $userId);
        $this->assertMembershipBindingAvailable($membershipMemberId, $userId, $allowDuplicateMembership);
        $this->assertOpenidAvailable($data['openid'] ?? ($existing['openid'] ?? null), $userId);

        $passwordSql = '';
        $params = [
            ':id' => $userId,
            ':username' => $username,
            ':nickname' => trim((string) ($data['nickname'] ?? $existing['nickname'])) ?: $phone,
            ':phone' => $phone,
            ':role' => (string) ($data['role'] ?? $existing['role'] ?? 'customer'),
            ':openid' => $this->normalizeOpenid($data['openid'] ?? ($existing['openid'] ?? null)),
            ':membership_member_id' => $membershipMemberId,
            ':status' => (string) ($data['status'] ?? $existing['status'] ?? 'active'),
            ':updated_at' => now(),
        ];

        if (!empty($data['password'])) {
            if (strlen((string) $data['password']) < 8) {
                throw new \RuntimeException('密码至少需要 8 位。');
            }
            $passwordSql = ', password_hash = :password_hash';
            $params[':password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        $sql = 'UPDATE mall_users
                SET username = :username, nickname = :nickname, phone = :phone, role = :role, openid = :openid,
                    membership_member_id = :membership_member_id, status = :status, updated_at = :updated_at'
            . $passwordSql .
            ' WHERE id = :id';
        $stmt = $this->db->mall()->prepare($sql);
        $stmt->execute($params);

        return $this->findUser($userId) ?? [];
    }

    public function updateUserStatus(int $userId, string $status): array
    {
        $normalizedStatus = $status === 'disabled' ? 'disabled' : 'active';
        return $this->updateUser($userId, ['status' => $normalizedStatus]);
    }

    public function resetPasswordToDefault(int $userId): string
    {
        $user = $this->findUser($userId);
        if (!$user) {
            throw new \RuntimeException('用户不存在。');
        }

        $password = $this->defaultPasswordFromPhone((string) ($user['phone'] ?? ''));
        $stmt = $this->db->mall()->prepare(
            'UPDATE mall_users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':updated_at' => now(),
            ':id' => $userId,
        ]);

        return $password;
    }

    public function loginCaptchaConfig(): array
    {
        return $this->captcha->clientConfig();
    }

    public function listActiveLockouts(): array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT al.id, al.lock_scope, al.identifier, al.user_id, al.failed_attempts, al.captcha_required,
                    al.locked_until, al.last_failed_at, al.created_at, al.updated_at,
                    u.username, u.nickname, u.phone, u.role, u.status
             FROM auth_lockouts al
             LEFT JOIN mall_users u ON u.id = al.user_id
             WHERE al.locked_until IS NOT NULL AND al.locked_until > :now
             ORDER BY al.locked_until DESC, al.id DESC'
        );
        $stmt->execute([':now' => now()]);
        $rows = $stmt->fetchAll();

        $grouped = [
            'users' => [],
            'ips' => [],
        ];

        foreach ($rows as $row) {
            $item = [
                'id' => (int) ($row['id'] ?? 0),
                'lock_scope' => (string) ($row['lock_scope'] ?? ''),
                'identifier' => (string) ($row['identifier'] ?? ''),
                'user_id' => !empty($row['user_id']) ? (int) $row['user_id'] : null,
                'failed_attempts' => (int) ($row['failed_attempts'] ?? 0),
                'captcha_required' => (int) ($row['captcha_required'] ?? 0) === 1,
                'locked_until' => $row['locked_until'] ?? null,
                'last_failed_at' => $row['last_failed_at'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];

            if (($row['lock_scope'] ?? '') === 'user') {
                $item['nickname'] = (string) ($row['nickname'] ?? '');
                $item['phone'] = (string) ($row['phone'] ?? '');
                $item['username'] = (string) ($row['username'] ?? '');
                $item['role'] = (string) ($row['role'] ?? '');
                $item['status'] = (string) ($row['status'] ?? '');
                $item['label'] = $this->lockoutUserLabel($row);
                $grouped['users'][] = $item;
                continue;
            }

            $item['label'] = (string) ($row['identifier'] ?? '');
            $grouped['ips'][] = $item;
        }

        return $grouped;
    }

    public function unlockLockout(int $lockoutId, Request $request): array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, lock_scope, identifier, user_id, failed_attempts, locked_until, last_failed_at
             FROM auth_lockouts
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $lockoutId]);
        $lockout = $stmt->fetch();
        if (!$lockout) {
            throw new \RuntimeException('锁定记录不存在。');
        }

        $delete = $this->db->mall()->prepare('DELETE FROM auth_lockouts WHERE id = :id');
        $delete->execute([':id' => $lockoutId]);

        $this->logger->info('auth_security', '管理员手动解锁登录限制', [
            'lock_scope' => (string) ($lockout['lock_scope'] ?? ''),
            'identifier' => (string) ($lockout['identifier'] ?? ''),
            'user_id' => !empty($lockout['user_id']) ? (int) $lockout['user_id'] : null,
            'failed_attempts' => (int) ($lockout['failed_attempts'] ?? 0),
            'locked_until' => $lockout['locked_until'] ?? null,
            'last_failed_at' => $lockout['last_failed_at'] ?? null,
        ], null, $request);

        return [
            'id' => (int) ($lockout['id'] ?? 0),
            'lock_scope' => (string) ($lockout['lock_scope'] ?? ''),
            'identifier' => (string) ($lockout['identifier'] ?? ''),
            'message' => '已解除锁定。',
        ];
    }

    public function authenticateWithPassword(
        string $identifier,
        string $password,
        Request $request,
        bool $useSecurityControls = true,
        bool $allowAdminUsername = false
    ): array
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '' || $password === '') {
            throw new \RuntimeException(self::LOGIN_FAILED_MESSAGE);
        }

        $user = $this->findAuthUserByPhone($normalizedIdentifier);
        if (!$user && $allowAdminUsername) {
            $user = $this->findAuthAdminByUsername($normalizedIdentifier);
        }

        if ($useSecurityControls) {
            $this->clearExpiredLockout('ip', $request->ip());
            if ($user) {
                $this->clearExpiredLockout('user', (string) $user['id']);
            }

            $this->assertNoActiveLoginLocks($request->ip(), $user, $request);
            $this->ensureCaptchaSatisfiedForLogin($normalizedIdentifier, $user, $request);
        }

        $passwordValid = $user
            && password_verify($password, (string) ($user['password_hash'] ?? ''))
            && (string) ($user['status'] ?? '') === 'active';

        if (!$passwordValid) {
            if ($useSecurityControls) {
                $failure = $this->recordFailedLoginAttempt($normalizedIdentifier, $user, $request);
                $captchaSubmitted = trim((string) $request->input('captcha_ticket', '')) !== ''
                    && trim((string) $request->input('captcha_randstr', '')) !== '';
                if (!empty($failure['locked'])) {
                    throw new ApiException(
                        self::LOGIN_LOCKED_MESSAGE,
                        'login_locked',
                        [
                            'lock_scope' => (string) ($failure['lock_scope'] ?? ''),
                            'locked_until' => $failure['locked_until'] ?? null,
                        ],
                        423
                    );
                }

                if (!empty($failure['captcha_required']) && !$captchaSubmitted) {
                    throw new ApiException(
                        '请先完成腾讯验证码校验后再登录。',
                        'captcha_required',
                        [
                            'captcha_required' => true,
                            'trigger_failed_attempts' => $this->captcha->triggerFailedAttempts(),
                        ],
                        403
                    );
                }
            }

            $this->logger->warning('auth', '登录失败', ['identifier' => $normalizedIdentifier], null, $request);
            throw new \RuntimeException(self::LOGIN_FAILED_MESSAGE);
        }

        if ($useSecurityControls) {
            $this->clearLoginLockoutState($request->ip(), $user, $request, 'password_login_success');
        }

        return $user;
    }

    public function authenticateWithOpenid(?string $openid, Request $request): array
    {
        if ($openid === null) {
            throw new \RuntimeException('尚未获取当前微信身份，请稍后重试。');
        }

        $user = $this->findUserByOpenid($openid);
        if (!$user || ($user['status'] ?? 'disabled') !== 'active') {
            $this->logger->warning(
                'wechat_auth',
                '微信登录失败，未找到已绑定的有效用户',
                $this->openidLogContext($openid),
                null,
                $request
            );
            throw new \RuntimeException('该微信未绑定用户，首次访问请使用账号密码登陆。');
        }

        return $user;
    }

    public function searchUsers(array $ids = [], string $keyword = '', int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;
        $conditions = [];
        $params = [];

        if ($ids !== []) {
            $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
            if ($normalizedIds === []) {
                return [
                    'items' => [],
                    'meta' => ['page' => $page, 'page_size' => $pageSize, 'total' => 0, 'total_pages' => 1],
                ];
            }

            $placeholders = [];
            foreach ($normalizedIds as $index => $id) {
                $placeholder = ':id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $id;
            }
            $conditions[] = 'id IN (' . implode(', ', $placeholders) . ')';
        }

        if ($keyword !== '') {
            $conditions[] = 'phone LIKE :keyword';
            $params[':keyword'] = '%' . trim($keyword) . '%';
        }

        $whereSql = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->db->mall()->prepare('SELECT COUNT(*) AS total FROM mall_users' . $whereSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, role,
                    CASE WHEN openid IS NOT NULL AND openid <> \'\' THEN 1 ELSE 0 END AS wechat_bound,
                    membership_member_id, status, last_login_at, created_at
             FROM mall_users'
            . $whereSql .
            ' ORDER BY id DESC
              LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => array_map(fn (array $item): array => $this->publicUserPayload($item), $stmt->fetchAll()),
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ];
    }

    public function toPublicUser(array $user): array
    {
        return $this->publicUserPayload($user);
    }

    public function findUser(int $userId): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, role, openid, membership_member_id, status, last_login_at, created_at
             FROM mall_users
             WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findUserByOpenid(string $openid): ?array
    {
        $normalizedOpenid = $this->normalizeOpenid($openid);
        if ($normalizedOpenid === null) {
            return null;
        }

        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, role, openid, membership_member_id, status, last_login_at, created_at
             FROM mall_users
             WHERE openid = :openid
             LIMIT 1'
        );
        $stmt->execute([':openid' => $normalizedOpenid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function bindOpenid(int $userId, string $openid, Request $request): array
    {
        $normalizedOpenid = $this->normalizeOpenid($openid);
        if ($normalizedOpenid === null) {
            throw new \RuntimeException('尚未获取当前微信身份，请稍后重试。');
        }

        $user = $this->findUser($userId);
        if (!$user) {
            throw new \RuntimeException('当前用户不存在。');
        }

        $currentOpenid = $this->normalizeOpenid($user['openid'] ?? null);
        if ($currentOpenid !== null && $currentOpenid === $normalizedOpenid) {
            $this->logger->info(
                'wechat_auth',
                '微信绑定已存在，跳过重复绑定',
                $this->openidLogContext($normalizedOpenid) + ['bind_user_id' => $userId],
                $userId,
                $request
            );

            return $user;
        }

        if ($currentOpenid !== null) {
            throw new \RuntimeException('当前账号已绑定微信，请先解绑后再绑定新的微信账号。');
        }

        $duplicate = $this->findUserByOpenid($normalizedOpenid);
        if ($duplicate && (int) $duplicate['id'] !== $userId) {
            $this->logger->warning(
                'wechat_auth',
                '微信绑定失败，openid 已绑定其他用户',
                $this->openidLogContext($normalizedOpenid) + [
                    'bind_user_id' => $userId,
                    'duplicate_user_id' => (int) $duplicate['id'],
                ],
                $userId,
                $request
            );
            throw new \RuntimeException('该微信已绑定其他用户，请先解绑后再操作。');
        }

        $stmt = $this->db->mall()->prepare(
            'UPDATE mall_users SET openid = :openid, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':openid' => $normalizedOpenid,
            ':updated_at' => now(),
            ':id' => $userId,
        ]);

        $this->logger->info(
            'wechat_auth',
            '微信绑定成功',
            $this->openidLogContext($normalizedOpenid) + [
                'bind_user_id' => $userId,
                'bind_phone' => (string) ($user['phone'] ?? ''),
            ],
            $userId,
            $request
        );

        return $this->findUser($userId) ?? [];
    }

    public function unbindOpenid(int $userId, Request $request): array
    {
        $user = $this->findUser($userId);
        if (!$user) {
            throw new \RuntimeException('当前用户不存在。');
        }

        $currentOpenid = $this->normalizeOpenid($user['openid'] ?? null);
        if ($currentOpenid === null) {
            throw new \RuntimeException('当前账号尚未绑定微信。');
        }

        $stmt = $this->db->mall()->prepare(
            'UPDATE mall_users SET openid = NULL, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':updated_at' => now(),
            ':id' => $userId,
        ]);

        $this->logger->info(
            'wechat_auth',
            '微信解绑成功',
            $this->openidLogContext($currentOpenid) + [
                'unbind_user_id' => $userId,
                'unbind_phone' => (string) ($user['phone'] ?? ''),
            ],
            $userId,
            $request
        );

        return $this->findUser($userId) ?? [];
    }

    public function addresses(int $userId): array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, receiver_name, receiver_phone, province, city, district, detail_address, postal_code, is_default
             FROM user_addresses
             WHERE user_id = :user_id
             ORDER BY is_default DESC, id DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function saveAddress(int $userId, array $data, ?int $addressId = null): array
    {
        $receiverName = trim((string) ($data['receiver_name'] ?? ''));
        $receiverPhone = trim((string) ($data['receiver_phone'] ?? ''));
        $province = trim((string) ($data['province'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $district = trim((string) ($data['district'] ?? ''));
        $detailAddress = trim((string) ($data['detail_address'] ?? ''));

        if ($receiverName === '' || $receiverPhone === '' || $province === '' || $city === '' || $district === '' || $detailAddress === '') {
            throw new \RuntimeException('请完整填写收货地址信息。');
        }

        $pdo = $this->db->mall();
        $pdo->beginTransaction();

        try {
            if (!empty($data['is_default'])) {
                $clearDefault = $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id');
                $clearDefault->execute([':user_id' => $userId]);
            }

            if ($addressId) {
                $stmt = $pdo->prepare(
                    'UPDATE user_addresses
                     SET receiver_name = :receiver_name, receiver_phone = :receiver_phone, province = :province, city = :city,
                         district = :district, detail_address = :detail_address, postal_code = :postal_code,
                         is_default = :is_default, updated_at = :updated_at
                     WHERE id = :id AND user_id = :user_id'
                );
                $stmt->execute([
                    ':receiver_name' => $receiverName,
                    ':receiver_phone' => $receiverPhone,
                    ':province' => $province,
                    ':city' => $city,
                    ':district' => $district,
                    ':detail_address' => $detailAddress,
                    ':postal_code' => trim((string) ($data['postal_code'] ?? '')),
                    ':is_default' => !empty($data['is_default']) ? 1 : 0,
                    ':updated_at' => now(),
                    ':id' => $addressId,
                    ':user_id' => $userId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO user_addresses (user_id, receiver_name, receiver_phone, province, city, district, detail_address, postal_code, is_default, created_at, updated_at)
                     VALUES (:user_id, :receiver_name, :receiver_phone, :province, :city, :district, :detail_address, :postal_code, :is_default, :created_at, :updated_at)'
                );
                $stmt->execute([
                    ':user_id' => $userId,
                    ':receiver_name' => $receiverName,
                    ':receiver_phone' => $receiverPhone,
                    ':province' => $province,
                    ':city' => $city,
                    ':district' => $district,
                    ':detail_address' => $detailAddress,
                    ':postal_code' => trim((string) ($data['postal_code'] ?? '')),
                    ':is_default' => !empty($data['is_default']) ? 1 : 0,
                    ':created_at' => now(),
                    ':updated_at' => now(),
                ]);
                $addressId = (int) $pdo->lastInsertId();
            }

            $pdo->commit();
            return $this->findAddress($userId, $addressId ?? 0) ?? [];
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function deleteAddress(int $userId, int $addressId): void
    {
        $stmt = $this->db->mall()->prepare('DELETE FROM user_addresses WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $addressId, ':user_id' => $userId]);
    }

    public function defaultAddress(int $userId): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, receiver_name, receiver_phone, province, city, district, detail_address, postal_code, is_default
             FROM user_addresses
             WHERE user_id = :user_id
             ORDER BY is_default DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAddress(int $userId, int $addressId): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, receiver_name, receiver_phone, province, city, district, detail_address, postal_code, is_default
             FROM user_addresses
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([':id' => $addressId, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function loginSecurityConfig(): array
    {
        $config = array_merge([
            'max_failed_attempts' => '10',
            'lock_minutes' => '60',
        ], $this->settings->getGroup('login_security'));

        return [
            'max_failed_attempts' => max(1, (int) ($config['max_failed_attempts'] ?? 10)),
            'lock_minutes' => max(1, (int) ($config['lock_minutes'] ?? 60)),
        ];
    }

    private function assertNoActiveLoginLocks(string $ip, ?array $user, Request $request): void
    {
        $this->clearExpiredLockout('ip', $ip);
        if ($user) {
            $this->clearExpiredLockout('user', (string) $user['id']);
        }

        $ipLockout = $this->findLockout('ip', $ip);
        if ($this->isLockoutActive($ipLockout)) {
            $this->logger->warning('auth_security', '登录请求命中 IP 锁定', [
                'ip' => $ip,
                'locked_until' => $ipLockout['locked_until'] ?? null,
                'failed_attempts' => (int) ($ipLockout['failed_attempts'] ?? 0),
            ], null, $request);

            throw new ApiException(
                self::LOGIN_LOCKED_MESSAGE,
                'login_locked',
                [
                    'lock_scope' => 'ip',
                    'locked_until' => $ipLockout['locked_until'] ?? null,
                ],
                423
            );
        }

        if (!$user) {
            return;
        }

        $userLockout = $this->findLockout('user', (string) $user['id']);
        if (!$this->isLockoutActive($userLockout)) {
            return;
        }

        $this->logger->warning('auth_security', '登录请求命中账号锁定', [
            'ip' => $ip,
            'user_id' => (int) ($user['id'] ?? 0),
            'locked_until' => $userLockout['locked_until'] ?? null,
            'failed_attempts' => (int) ($userLockout['failed_attempts'] ?? 0),
        ], (int) ($user['id'] ?? 0), $request);

        throw new ApiException(
            self::LOGIN_LOCKED_MESSAGE,
            'login_locked',
            [
                'lock_scope' => 'user',
                'locked_until' => $userLockout['locked_until'] ?? null,
            ],
            423
        );
    }

    private function ensureCaptchaSatisfiedForLogin(string $identifier, ?array $user, Request $request): void
    {
        if (!$this->requiresCaptchaForLogin($request->ip(), $user)) {
            return;
        }

        $ticket = trim((string) $request->input('captcha_ticket', ''));
        $randstr = trim((string) $request->input('captcha_randstr', ''));
        if ($ticket === '' || $randstr === '') {
            $this->logger->info('auth_security', '登录前需要腾讯验证码', [
                'identifier' => $identifier,
                'ip' => $request->ip(),
                'user_id' => !empty($user['id']) ? (int) $user['id'] : null,
                'trigger_failed_attempts' => $this->captcha->triggerFailedAttempts(),
            ], !empty($user['id']) ? (int) $user['id'] : null, $request);

            throw new ApiException(
                '请先完成腾讯验证码校验后再登录。',
                'captcha_required',
                [
                    'captcha_required' => true,
                    'trigger_failed_attempts' => $this->captcha->triggerFailedAttempts(),
                ],
                403
            );
        }

        try {
            $this->captcha->verifyLoginCaptcha($ticket, $randstr, $request->ip(), $request);
        } catch (\RuntimeException $exception) {
            throw new ApiException($exception->getMessage(), 'captcha_invalid', [], 403);
        }
    }

    private function requiresCaptchaForLogin(string $ip, ?array $user): bool
    {
        if (!$this->captcha->isEnabled()) {
            return false;
        }

        $threshold = $this->captcha->triggerFailedAttempts();
        $ipLockout = $this->findLockout('ip', $ip);
        if ($ipLockout && (int) ($ipLockout['failed_attempts'] ?? 0) >= $threshold) {
            return true;
        }

        if (!$user) {
            return false;
        }

        $userLockout = $this->findLockout('user', (string) $user['id']);
        return $userLockout && (int) ($userLockout['failed_attempts'] ?? 0) >= $threshold;
    }

    private function recordFailedLoginAttempt(string $identifier, ?array $user, Request $request): array
    {
        $config = $this->loginSecurityConfig();
        $captchaEnabled = $this->captcha->isEnabled();
        $captchaThreshold = $this->captcha->triggerFailedAttempts();

        $ipLockout = $this->incrementLockout(
            'ip',
            $request->ip(),
            null,
            $captchaEnabled,
            $captchaThreshold,
            $config['max_failed_attempts'],
            $config['lock_minutes']
        );
        $userLockout = $user
            ? $this->incrementLockout(
                'user',
                (string) $user['id'],
                (int) $user['id'],
                $captchaEnabled,
                $captchaThreshold,
                $config['max_failed_attempts'],
                $config['lock_minutes']
            )
            : null;

        $lockScope = '';
        $lockedUntil = null;
        if ($this->isLockoutActive($userLockout)) {
            $lockScope = 'user';
            $lockedUntil = $userLockout['locked_until'] ?? null;
        } elseif ($this->isLockoutActive($ipLockout)) {
            $lockScope = 'ip';
            $lockedUntil = $ipLockout['locked_until'] ?? null;
        }

        $captchaRequired = $lockScope === '' && $captchaEnabled && (
            (int) ($ipLockout['captcha_required'] ?? 0) === 1
            || (int) ($userLockout['captcha_required'] ?? 0) === 1
        );

        $context = [
            'identifier' => $identifier,
            'ip' => $request->ip(),
            'user_id' => !empty($user['id']) ? (int) $user['id'] : null,
            'max_failed_attempts' => $config['max_failed_attempts'],
            'lock_minutes' => $config['lock_minutes'],
            'captcha_enabled' => $captchaEnabled,
            'captcha_trigger_failed_attempts' => $captchaThreshold,
            'ip_failed_attempts' => (int) ($ipLockout['failed_attempts'] ?? 0),
            'ip_locked_until' => $ipLockout['locked_until'] ?? null,
            'user_failed_attempts' => (int) ($userLockout['failed_attempts'] ?? 0),
            'user_locked_until' => $userLockout['locked_until'] ?? null,
            'captcha_required' => $captchaRequired,
            'lock_scope' => $lockScope,
        ];

        $this->logger->warning('auth_security', '登录失败已计入安全策略', $context, !empty($user['id']) ? (int) $user['id'] : null, $request);

        if ($lockScope !== '') {
            $this->logger->warning('auth_security', '登录失败触发自动锁定', $context, !empty($user['id']) ? (int) $user['id'] : null, $request);
        }

        return [
            'locked' => $lockScope !== '',
            'lock_scope' => $lockScope,
            'locked_until' => $lockedUntil,
            'captcha_required' => $captchaRequired,
            'ip_lockout' => $ipLockout,
            'user_lockout' => $userLockout,
        ];
    }

    private function incrementLockout(
        string $scope,
        string $identifier,
        ?int $userId,
        bool $captchaEnabled,
        int $captchaThreshold,
        int $maxFailedAttempts,
        int $lockMinutes
    ): array {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            return [];
        }

        $existing = $this->findLockout($scope, $normalizedIdentifier);
        if ($existing && !$this->isLockoutActive($existing) && !empty($existing['locked_until'])) {
            $this->deleteLockout($scope, $normalizedIdentifier);
            $existing = null;
        }

        $failedAttempts = (int) ($existing['failed_attempts'] ?? 0) + 1;
        $captchaRequired = $captchaEnabled && $failedAttempts >= $captchaThreshold;
        $lockedUntil = null;
        if ($failedAttempts >= $maxFailedAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + ($lockMinutes * 60));
        }

        $stmt = $this->db->mall()->prepare(
            'INSERT INTO auth_lockouts (lock_scope, identifier, user_id, failed_attempts, captcha_required, locked_until, last_failed_at, created_at, updated_at)
             VALUES (:lock_scope, :identifier, :user_id, :failed_attempts, :captcha_required, :locked_until, :last_failed_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                failed_attempts = VALUES(failed_attempts),
                captcha_required = VALUES(captcha_required),
                locked_until = VALUES(locked_until),
                last_failed_at = VALUES(last_failed_at),
                updated_at = VALUES(updated_at)'
        );
        $timestamp = now();
        $stmt->execute([
            ':lock_scope' => $scope,
            ':identifier' => $normalizedIdentifier,
            ':user_id' => $userId,
            ':failed_attempts' => $failedAttempts,
            ':captcha_required' => $captchaRequired ? 1 : 0,
            ':locked_until' => $lockedUntil,
            ':last_failed_at' => $timestamp,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);

        return [
            'lock_scope' => $scope,
            'identifier' => $normalizedIdentifier,
            'user_id' => $userId,
            'failed_attempts' => $failedAttempts,
            'captcha_required' => $captchaRequired ? 1 : 0,
            'locked_until' => $lockedUntil,
            'last_failed_at' => $timestamp,
        ];
    }

    private function clearExpiredLockout(string $scope, string $identifier): void
    {
        $lockout = $this->findLockout($scope, $identifier);
        if (!$lockout) {
            return;
        }

        if ($this->isLockoutActive($lockout) || empty($lockout['locked_until'])) {
            return;
        }

        $this->deleteLockout($scope, $identifier);
    }

    private function clearLoginLockoutState(string $ip, ?array $user, ?Request $request = null, string $reason = ''): void
    {
        $clearedIp = $this->deleteLockout('ip', $ip);
        $clearedUser = $user ? $this->deleteLockout('user', (string) $user['id']) : false;

        if (!$clearedIp && !$clearedUser) {
            return;
        }

        $this->logger->info('auth_security', '登录安全状态已清理', [
            'reason' => $reason,
            'ip' => $ip,
            'user_id' => !empty($user['id']) ? (int) $user['id'] : null,
            'cleared_ip' => $clearedIp,
            'cleared_user' => $clearedUser,
        ], !empty($user['id']) ? (int) $user['id'] : null, $request);
    }

    private function findLockout(string $scope, string $identifier): ?array
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            return null;
        }

        $stmt = $this->db->mall()->prepare(
            'SELECT id, lock_scope, identifier, user_id, failed_attempts, captcha_required, locked_until, last_failed_at, created_at, updated_at
             FROM auth_lockouts
             WHERE lock_scope = :lock_scope AND identifier = :identifier
             LIMIT 1'
        );
        $stmt->execute([
            ':lock_scope' => $scope,
            ':identifier' => $normalizedIdentifier,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function deleteLockout(string $scope, string $identifier): bool
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            return false;
        }

        $stmt = $this->db->mall()->prepare(
            'DELETE FROM auth_lockouts WHERE lock_scope = :lock_scope AND identifier = :identifier'
        );
        $stmt->execute([
            ':lock_scope' => $scope,
            ':identifier' => $normalizedIdentifier,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function isLockoutActive(?array $lockout): bool
    {
        if (!$lockout) {
            return false;
        }

        $lockedUntil = trim((string) ($lockout['locked_until'] ?? ''));
        if ($lockedUntil === '') {
            return false;
        }

        $expiresAt = strtotime($lockedUntil);
        return $expiresAt !== false && $expiresAt > time();
    }

    private function lockoutUserLabel(array $row): string
    {
        $nickname = trim((string) ($row['nickname'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));
        $username = trim((string) ($row['username'] ?? ''));

        if ($nickname !== '' && $phone !== '') {
            return $nickname . ' / ' . $phone;
        }
        if ($nickname !== '') {
            return $nickname;
        }
        if ($phone !== '') {
            return $phone;
        }
        if ($username !== '') {
            return $username;
        }

        return '用户 #' . (int) ($row['user_id'] ?? 0);
    }

    private function assertPhoneUnique(string $phone, ?int $excludeUserId): void
    {
        if ($phone === '') {
            return;
        }

        $duplicate = $this->findByPhone($phone);
        if ($duplicate && (int) $duplicate['id'] !== (int) ($excludeUserId ?? 0)) {
            throw new \RuntimeException('手机号已被其他用户使用。');
        }
    }

    private function assertOpenidAvailable(mixed $openid, ?int $excludeUserId): void
    {
        $normalizedOpenid = $this->normalizeOpenid($openid);
        if ($normalizedOpenid === null) {
            return;
        }

        $duplicate = $this->findUserByOpenid($normalizedOpenid);
        if ($duplicate && (int) $duplicate['id'] !== (int) ($excludeUserId ?? 0)) {
            throw new \RuntimeException('该微信 OpenID 已绑定其他用户。');
        }
    }

    private function assertMembershipBindingAvailable(?int $membershipMemberId, ?int $excludeUserId, bool $allowDuplicateMembership): void
    {
        if (!$membershipMemberId) {
            return;
        }

        $duplicate = $this->findByMembershipMemberId($membershipMemberId);
        if (!$duplicate || (int) $duplicate['id'] === (int) ($excludeUserId ?? 0)) {
            return;
        }

        if ($allowDuplicateMembership) {
            return;
        }

        throw new ConfirmRequiredException(
            '该会员已绑定其他用户，确认仍要继续绑定吗？',
            [
                'bound_user' => [
                    'id' => (int) $duplicate['id'],
                    'username' => (string) ($duplicate['username'] ?? ''),
                    'nickname' => (string) ($duplicate['nickname'] ?? ''),
                    'phone' => (string) ($duplicate['phone'] ?? ''),
                ],
                'membership_member_id' => $membershipMemberId,
            ]
        );
    }

    private function findByUsername(string $username): ?array
    {
        $stmt = $this->db->mall()->prepare('SELECT id, username FROM mall_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findByPhone(string $phone): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone
             FROM mall_users
             WHERE phone = :phone
             LIMIT 1'
        );
        $stmt->execute([':phone' => $phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findByMembershipMemberId(int $membershipMemberId): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, membership_member_id
             FROM mall_users
             WHERE membership_member_id = :membership_member_id
             LIMIT 1'
        );
        $stmt->execute([':membership_member_id' => $membershipMemberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findAuthUserByPhone(string $phone): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, password_hash, role, openid, membership_member_id, status, last_login_at, created_at
             FROM mall_users
             WHERE phone = :phone
             LIMIT 1'
        );
        $stmt->execute([':phone' => $phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findAuthAdminByUsername(string $username): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, nickname, phone, password_hash, role, openid, membership_member_id, status, last_login_at, created_at
             FROM mall_users
             WHERE username = :username AND role = :role
             LIMIT 1'
        );
        $stmt->execute([
            ':username' => $username,
            ':role' => 'admin',
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function touchLastLogin(int $userId): void
    {
        $update = $this->db->mall()->prepare('UPDATE mall_users SET last_login_at = :last_login_at WHERE id = :id');
        $update->execute([':last_login_at' => now(), ':id' => $userId]);
    }

    private function publicUserPayload(array $user): array
    {
        $openid = trim((string) ($user['openid'] ?? ''));

        return [
            'id' => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'nickname' => (string) ($user['nickname'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'role' => (string) ($user['role'] ?? 'customer'),
            'status' => (string) ($user['status'] ?? 'active'),
            'membership_member_id' => !empty($user['membership_member_id']) ? (int) $user['membership_member_id'] : null,
            'wechat_bound' => array_key_exists('wechat_bound', $user)
                ? (int) ($user['wechat_bound'] ?? 0) === 1
                : $openid !== '',
            'last_login_at' => $user['last_login_at'] ?? null,
            'created_at' => $user['created_at'] ?? null,
        ];
    }

    private function usernameFromPhone(string $phone): string
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            throw new \RuntimeException('手机号不能为空。');
        }

        return $normalized;
    }

    private function defaultPasswordFromPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 8) {
            throw new \RuntimeException('请填写至少 8 位手机号，新用户初始密码默认为手机号后 8 位。');
        }

        return substr($digits, -8);
    }

    private function normalizeOpenid(mixed $openid): ?string
    {
        $normalized = trim((string) ($openid ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function openidLogContext(string $openid): array
    {
        return [
            'openid_masked' => substr($openid, 0, 4) . '***' . substr($openid, -4),
            'openid_hash' => sha1($openid),
        ];
    }
}
