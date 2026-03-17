<?php

declare(strict_types=1);

namespace Mall\Core;

class RateLimiter
{
    public function __construct(
        private readonly string $cacheFile,
        private readonly RedisClient $redis
    ) {
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        [$attempts, $expiresAt] = $this->getRecord($key);
        if ($expiresAt < time()) {
            $this->clear($key);
            return false;
        }

        return $attempts >= $maxAttempts;
    }

    public function hit(string $key, int $windowSeconds): int
    {
        if ($this->redis->isAvailable()) {
            $redisKey = 'rate:' . $key;
            $attempts = $this->redis->incr($redisKey);
            if ($attempts === 1) {
                $this->redis->expire($redisKey, $windowSeconds);
            }
            return $attempts;
        }

        $records = $this->readRecords();
        [$attempts, $expiresAt] = $records[$key] ?? [0, 0];
        if ($expiresAt < time()) {
            $attempts = 0;
        }

        $attempts++;
        $records[$key] = [$attempts, time() + $windowSeconds];
        $this->writeRecords($records);
        return $attempts;
    }

    public function clear(string $key): void
    {
        if ($this->redis->isAvailable()) {
            $this->redis->del('rate:' . $key);
            return;
        }

        $records = $this->readRecords();
        unset($records[$key]);
        $this->writeRecords($records);
    }

    private function getRecord(string $key): array
    {
        if ($this->redis->isAvailable()) {
            $value = $this->redis->get('rate:' . $key);
            return [is_string($value) ? (int) $value : 0, time() + 1];
        }

        $records = $this->readRecords();
        return $records[$key] ?? [0, 0];
    }

    private function readRecords(): array
    {
        if (!is_file($this->cacheFile)) {
            return [];
        }

        $content = file_get_contents($this->cacheFile);
        $decoded = json_decode((string) $content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeRecords(array $records): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($this->cacheFile, json_encode_unicode($records));
    }
}
