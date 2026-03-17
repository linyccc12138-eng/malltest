<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\Crypto;
use Mall\Core\DatabaseManager;
use Mall\Core\Logger;
use PDO;
use PDOException;

class SettingsService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Crypto $crypto,
        private readonly Logger $logger
    ) {
    }

    public function getGroup(string $group): array
    {
        try {
            $pdo = $this->db->mall();
            $stmt = $pdo->prepare(
                'SELECT setting_key, setting_value, is_encrypted
                 FROM system_settings
                 WHERE setting_group = :setting_group
                 ORDER BY setting_key ASC'
            );
            $stmt->execute([':setting_group' => $group]);
            $rows = $stmt->fetchAll();
        } catch (PDOException) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            if ((int) $row['is_encrypted'] === 1) {
                $value = $this->crypto->decrypt((string) $value) ?? '';
            }
            $result[$row['setting_key']] = $value;
        }

        return $result;
    }

    public function getValue(string $group, string $key, mixed $default = null): mixed
    {
        $groupValues = $this->getGroup($group);
        return $groupValues[$key] ?? $default;
    }

    public function saveGroup(string $group, array $values, array $encryptedKeys = []): void
    {
        $pdo = $this->db->mall();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO system_settings (setting_group, setting_key, setting_value, is_encrypted, updated_at)
                 VALUES (:setting_group, :setting_key, :setting_value, :is_encrypted, :updated_at)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted), updated_at = VALUES(updated_at)'
            );

            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode_unicode($value);
                }

                $isEncrypted = in_array($key, $encryptedKeys, true);
                $storedValue = $isEncrypted ? $this->crypto->encrypt((string) $value) : (string) $value;

                $stmt->execute([
                    ':setting_group' => $group,
                    ':setting_key' => (string) $key,
                    ':setting_value' => $storedValue,
                    ':is_encrypted' => $isEncrypted ? 1 : 0,
                    ':updated_at' => now(),
                ]);
            }

            $pdo->commit();
            $this->logger->info('settings', '系统设置已更新', ['group' => $group]);
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function allForAdmin(): array
    {
        return [
            'membership_mysql' => array_merge($this->defaults()['membership_mysql'], $this->getGroup('membership_mysql')),
            'wechat_pay' => array_merge($this->defaults()['wechat_pay'], $this->getGroup('wechat_pay')),
            'wechat_service_account' => array_merge($this->defaults()['wechat_service_account'], $this->getGroup('wechat_service_account')),
            'log' => array_merge($this->defaults()['log'], $this->getGroup('log')),
            'notifications' => array_merge($this->defaults()['notifications'], $this->getGroup('notifications')),
        ];
    }

    public function testMembershipConnection(array $config): array
    {
        $pdo = $this->buildPdo($config);
        $stmt = $pdo->query('SELECT COUNT(*) AS total FROM member');
        $row = $stmt->fetch();

        return [
            'success' => true,
            'message' => '会员数据库连接成功。',
            'total_members' => (int) ($row['total'] ?? 0),
        ];
    }

    public function buildPdo(array $config): PDO
    {
        $required = ['host', 'port', 'database', 'username'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \RuntimeException('缺少数据库配置项：' . $field);
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        return new PDO(
            $dsn,
            $config['username'],
            $config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    private function defaults(): array
    {
        return [
            'membership_mysql' => [
                'host' => '',
                'port' => '3306',
                'database' => '',
                'username' => '',
                'password' => '',
                'charset' => 'utf8mb4',
            ],
            'wechat_pay' => [
                'app_id' => '',
                'merchant_id' => '',
                'merchant_serial_no' => '',
                'api_v3_key' => '',
                'private_key_content' => '',
                'platform_cert_content' => '',
                'notify_url' => '',
                'pay_mode' => 'JSAPI',
            ],
            'wechat_service_account' => [
                'app_id' => '',
                'app_secret' => '',
            ],
            'log' => [
                'min_level' => 'info',
                'retention_days' => '30',
                'max_size_mb' => '10',
            ],
            'notifications' => [
                'admin_paid_enabled' => '1',
                'admin_paid_template_id' => '',
                'admin_cancelled_enabled' => '1',
                'admin_cancelled_template_id' => '',
                'user_created_enabled' => '1',
                'user_created_template_id' => '',
                'user_paid_enabled' => '1',
                'user_paid_template_id' => '',
                'user_shipped_enabled' => '1',
                'user_shipped_template_id' => '',
                'user_completed_enabled' => '1',
                'user_completed_template_id' => '',
                'user_closed_enabled' => '1',
                'user_closed_template_id' => '',
            ],
        ];
    }
}