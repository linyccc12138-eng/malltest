<?php

declare(strict_types=1);

namespace Mall\Core;

use PDOException;

class Logger
{
    private const LEVEL_WEIGHTS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
    ];

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly string $logPath,
        private readonly Config $config,
        private readonly SessionManager $session
    ) {
    }

    public function debug(string $channel, string $message, array $context = [], ?int $userId = null, ?Request $request = null): void
    {
        $this->log('debug', $channel, $message, $context, $userId, $request);
    }

    public function info(string $channel, string $message, array $context = [], ?int $userId = null, ?Request $request = null): void
    {
        $this->log('info', $channel, $message, $context, $userId, $request);
    }

    public function warning(string $channel, string $message, array $context = [], ?int $userId = null, ?Request $request = null): void
    {
        $this->log('warning', $channel, $message, $context, $userId, $request);
    }

    public function error(string $channel, string $message, array $context = [], ?int $userId = null, ?Request $request = null): void
    {
        $this->log('error', $channel, $message, $context, $userId, $request);
    }

    public function critical(string $channel, string $message, array $context = [], ?int $userId = null, ?Request $request = null): void
    {
        $this->log('critical', $channel, $message, $context, $userId, $request);
    }

    public function log(
        string $level,
        string $channel,
        string $message,
        array $context = [],
        ?int $userId = null,
        ?Request $request = null
    ): void {
        if (!$this->shouldLog($level)) {
            return;
        }

        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }

        $line = sprintf(
            "[%s] %s.%s %s %s\n",
            now(),
            strtoupper($level),
            strtoupper($channel),
            $message,
            json_encode_unicode($context)
        );

        $file = $this->logPath . '/app-' . date('Y-m-d') . '.log';
        $this->rotateIfNeeded($file);
        file_put_contents($file, $line, FILE_APPEND);

        try {
            $pdo = $this->db->mall();
            $stmt = $pdo->prepare(
                'INSERT INTO system_logs (level, channel, message, context_json, user_id, ip_address, user_agent, created_at)
                 VALUES (:level, :channel, :message, :context_json, :user_id, :ip_address, :user_agent, :created_at)'
            );
            $stmt->execute([
                ':level' => $level,
                ':channel' => $channel,
                ':message' => $message,
                ':context_json' => json_encode_unicode($context),
                ':user_id' => $userId ?? $this->session->get('auth_user_id'),
                ':ip_address' => $request?->ip(),
                ':user_agent' => $request?->userAgent(),
                ':created_at' => now(),
            ]);
        } catch (PDOException) {
            // 数据库日志写入失败时，保留文件日志即可。
        }
    }

    private function shouldLog(string $level): bool
    {
        $minLevel = $this->runtimeSetting('min_level', (string) $this->config->get('logging.min_level', 'info'));
        $current = self::LEVEL_WEIGHTS[$level] ?? 200;
        $minimum = self::LEVEL_WEIGHTS[$minLevel] ?? 200;

        return $current >= $minimum;
    }

    private function rotateIfNeeded(string $file): void
    {
        $maxSizeMb = (int) $this->runtimeSetting('max_size_mb', (string) $this->config->get('logging.max_size_mb', 10));
        if (is_file($file) && filesize($file) > $maxSizeMb * 1024 * 1024) {
            rename($file, $file . '.' . time());
        }
    }

    private function runtimeSetting(string $key, string $default): string
    {
        try {
            $pdo = $this->db->mall();
            $stmt = $pdo->prepare(
                'SELECT setting_value FROM system_settings WHERE setting_group = :setting_group AND setting_key = :setting_key LIMIT 1'
            );
            $stmt->execute([':setting_group' => 'log', ':setting_key' => $key]);
            $row = $stmt->fetch();

            return (string) ($row['setting_value'] ?? $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}