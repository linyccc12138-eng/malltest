<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\ConfirmRequiredException;
use Mall\Core\DatabaseManager;
use Mall\Core\Logger;
use Mall\Core\RateLimiter;
use Mall\Core\Request;
use Mall\Core\SessionManager;
use PDO;

class UserService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SessionManager $session,
        private readonly Logger $logger,
        private readonly RateLimiter $rateLimiter
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

    public function login(string $username, string $password, Request $request): array
    {
        $key = 'login:' . $request->ip();
        if ($this->rateLimiter->tooManyAttempts($key, 5, 60)) {
            throw new \RuntimeException('登录过于频繁，请 1 分钟后再试。');
        }

        $identifier = trim($username);
        $stmt = $this->db->mall()->prepare(
            'SELECT id, username, phone, password_hash, role, status
             FROM mall_users
             WHERE username = :username OR phone = :phone
             LIMIT 1'
        );
        $stmt->execute([
            ':username' => $identifier,
            ':phone' => $identifier,
        ]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash']) || $user['status'] !== 'active') {
            $this->rateLimiter->hit($key, 60);
            $this->logger->warning('auth', '登录失败', ['username' => $identifier], null, $request);
            throw new \RuntimeException('用户名或密码错误，或账号已停用。');
        }

        $this->rateLimiter->clear($key);
        $this->session->put('auth_user_id', (int) $user['id']);

        $update = $this->db->mall()->prepare('UPDATE mall_users SET last_login_at = :last_login_at WHERE id = :id');
        $update->execute([':last_login_at' => now(), ':id' => (int) $user['id']]);

        $this->logger->info('auth', '用户登录成功', ['username' => $identifier], (int) $user['id'], $request);

        return [
            'id' => (int) $user['id'],
            'role' => (string) $user['role'],
        ];
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
            'UPDATE mall_users SET nickname = :nickname, phone = :phone, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':nickname' => $nickname,
            ':phone' => $phone,
            ':updated_at' => now(),
            ':id' => $userId,
        ]);

        return $this->findUser($userId) ?? [];
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
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
            'SELECT id, username, nickname, phone, role, openid, membership_member_id, status, last_login_at, created_at
             FROM mall_users
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
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
        $username = trim((string) ($data['username'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $membershipMemberId = !empty($data['membership_member_id']) ? (int) $data['membership_member_id'] : null;
        $allowDuplicateMembership = !empty($data['allow_duplicate_membership']);

        if ($username === '') {
            throw new \RuntimeException('用户名不能为空。');
        }
        if ($this->findByUsername($username)) {
            throw new \RuntimeException('用户名已存在。');
        }

        $password = $this->defaultPasswordFromPhone($phone);
        $this->assertPhoneUnique($phone, null);
        $this->assertMembershipBindingAvailable($membershipMemberId, null, $allowDuplicateMembership);

        $stmt = $this->db->mall()->prepare(
            'INSERT INTO mall_users (username, password_hash, nickname, phone, role, openid, membership_member_id, status, created_at, updated_at)
             VALUES (:username, :password_hash, :nickname, :phone, :role, :openid, :membership_member_id, :status, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':nickname' => trim((string) ($data['nickname'] ?? '')) ?: $username,
            ':phone' => $phone,
            ':role' => (string) ($data['role'] ?? 'customer'),
            ':openid' => trim((string) ($data['openid'] ?? '')),
            ':membership_member_id' => $membershipMemberId,
            ':status' => (string) ($data['status'] ?? 'active'),
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return $this->findUser((int) $this->db->mall()->lastInsertId()) ?? [];
    }

    public function updateUser(int $userId, array $data): array
    {
        $existing = $this->findUser($userId);
        if (!$existing) {
            throw new \RuntimeException('用户不存在。');
        }

        $username = trim((string) ($data['username'] ?? $existing['username']));
        if ($username === '') {
            throw new \RuntimeException('用户名不能为空。');
        }

        $duplicate = $this->findByUsername($username);
        if ($duplicate && (int) $duplicate['id'] !== $userId) {
            throw new \RuntimeException('用户名已被占用。');
        }

        $phone = trim((string) ($data['phone'] ?? $existing['phone'] ?? ''));
        $membershipMemberId = array_key_exists('membership_member_id', $data)
            ? (!empty($data['membership_member_id']) ? (int) $data['membership_member_id'] : null)
            : ($existing['membership_member_id'] !== null ? (int) $existing['membership_member_id'] : null);
        $allowDuplicateMembership = !empty($data['allow_duplicate_membership']);

        $this->assertPhoneUnique($phone, $userId);
        $this->assertMembershipBindingAvailable($membershipMemberId, $userId, $allowDuplicateMembership);

        $passwordSql = '';
        $params = [
            ':id' => $userId,
            ':username' => $username,
            ':nickname' => trim((string) ($data['nickname'] ?? $existing['nickname'])) ?: $username,
            ':phone' => $phone,
            ':role' => (string) ($data['role'] ?? $existing['role'] ?? 'customer'),
            ':openid' => trim((string) ($data['openid'] ?? $existing['openid'] ?? '')),
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

    public function resetPasswordToDefault(int $userId): void
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
            'SELECT id, username, nickname, membership_member_id
             FROM mall_users
             WHERE membership_member_id = :membership_member_id
             LIMIT 1'
        );
        $stmt->execute([':membership_member_id' => $membershipMemberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function defaultPasswordFromPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 8) {
            throw new \RuntimeException('请填写至少 8 位手机号，新用户初始密码默认为手机号后 8 位。');
        }

        return substr($digits, -8);
    }
}
