<?php

declare(strict_types=1);

namespace Mall\Core;

use PDO;
use PDOException;

class DatabaseManager
{
    private Config $config;
    private array $connections = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function mall(): PDO
    {
        return $this->connection('mall');
    }

    public function membership(): PDO
    {
        return $this->connection('membership');
    }

    public function connection(string $name): PDO
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = $this->config->get('database.' . $name);
        if (!is_array($config)) {
            throw new PDOException('数据库配置不存在: ' . $name);
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->connections[$name] = $pdo;

        return $pdo;
    }
}