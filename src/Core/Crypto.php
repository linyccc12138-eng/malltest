<?php

declare(strict_types=1);

namespace Mall\Core;

class Crypto
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = hash('sha256', $key, true);
    }

    public function encrypt(string $plainText): string
    {
        $iv = random_bytes(16);
        $cipherText = openssl_encrypt(
            $plainText,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipherText === false) {
            throw new \RuntimeException('敏感信息加密失败');
        }

        $mac = hash_hmac('sha256', $iv . $cipherText, $this->key);

        return base64_encode(json_encode_unicode([
            'iv' => base64_encode($iv),
            'value' => base64_encode($cipherText),
            'mac' => $mac,
        ]));
    }

    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $decoded = json_decode((string) base64_decode($payload, true), true);
        if (!is_array($decoded)) {
            return null;
        }

        $iv = base64_decode((string) ($decoded['iv'] ?? ''), true);
        $cipherText = base64_decode((string) ($decoded['value'] ?? ''), true);
        $mac = (string) ($decoded['mac'] ?? '');

        if ($iv === false || $cipherText === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $iv . $cipherText, $this->key);
        if (!hash_equals($expected, $mac)) {
            return null;
        }

        $plainText = openssl_decrypt(
            $cipherText,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plainText === false ? null : $plainText;
    }
}