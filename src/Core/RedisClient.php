<?php

declare(strict_types=1);

namespace Mall\Core;

class RedisClient
{
    public function __construct(private readonly Config $config)
    {
    }

    public function isAvailable(): bool
    {
        return $this->ping();
    }

    public function ping(): bool
    {
        $result = $this->command(['PING']);
        return $result === 'PONG';
    }

    public function get(string $key): ?string
    {
        $result = $this->command(['GET', $key]);
        return is_string($result) ? $result : null;
    }

    public function set(string $key, string $value): bool
    {
        return $this->command(['SET', $key, $value]) === 'OK';
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        return $this->command(['SETEX', $key, (string) $ttl, $value]) === 'OK';
    }

    public function expire(string $key, int $ttl): bool
    {
        return $this->command(['EXPIRE', $key, (string) $ttl]) === 1;
    }

    public function del(string $key): int
    {
        $result = $this->command(['DEL', $key]);
        return is_int($result) ? $result : 0;
    }

    public function incr(string $key): int
    {
        $result = $this->command(['INCR', $key]);
        return is_int($result) ? $result : 0;
    }

    private function command(array $parts): mixed
    {
        $config = $this->config->get('database.redis');
        if (!is_array($config)) {
            return null;
        }

        $connection = @stream_socket_client(
            sprintf('tcp://%s:%d', $config['host'], (int) $config['port']),
            $errno,
            $errstr,
            (float) $config['timeout']
        );

        if (!is_resource($connection)) {
            return null;
        }

        stream_set_timeout($connection, (int) ceil((float) $config['timeout']));

        if (!empty($config['password'])) {
            $this->write($connection, ['AUTH', $config['password']]);
            $this->read($connection);
        }

        if (!empty($config['database'])) {
            $this->write($connection, ['SELECT', (string) $config['database']]);
            $this->read($connection);
        }

        $this->write($connection, $parts);
        $response = $this->read($connection);
        fclose($connection);

        return $response;
    }

    private function write($connection, array $parts): void
    {
        $payload = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $value = (string) $part;
            $payload .= '$' . strlen($value) . "\r\n" . $value . "\r\n";
        }
        fwrite($connection, $payload);
    }

    private function read($connection): mixed
    {
        $line = fgets($connection);
        if ($line === false) {
            return null;
        }

        $prefix = $line[0];
        $payload = substr(rtrim($line, "\r\n"), 1);

        return match ($prefix) {
            '+' => $payload,
            '-' => null,
            ':' => (int) $payload,
            '$' => $this->readBulkString($connection, (int) $payload),
            '*' => $this->readArray($connection, (int) $payload),
            default => null,
        };
    }

    private function readBulkString($connection, int $length): ?string
    {
        if ($length < 0) {
            return null;
        }

        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($connection, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }
        fread($connection, 2);

        return $data;
    }

    private function readArray($connection, int $count): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->read($connection);
        }

        return $items;
    }
}
