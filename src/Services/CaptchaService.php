<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\Logger;
use Mall\Core\Request;

class CaptchaService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly Logger $logger
    ) {
    }

    public function config(): array
    {
        return array_merge($this->defaults(), $this->settings->getGroup('captcha'));
    }

    public function clientConfig(): array
    {
        $config = $this->config();

        return [
            'enabled' => $this->isEnabled($config),
            'app_id' => $this->isEnabled($config) ? (string) ($config['app_id'] ?? '') : '',
            'trigger_failed_attempts' => $this->triggerFailedAttempts($config),
        ];
    }

    public function isEnabled(?array $config = null): bool
    {
        $resolved = $config ?? $this->config();

        return trim((string) ($resolved['app_id'] ?? '')) !== ''
            && trim((string) ($resolved['app_secret_key'] ?? '')) !== '';
    }

    public function triggerFailedAttempts(?array $config = null): int
    {
        $resolved = $config ?? $this->config();
        return max(1, (int) ($resolved['trigger_failed_attempts'] ?? 3));
    }

    public function verifyLoginCaptcha(
        ?string $ticket,
        ?string $randstr,
        string $userIp = '',
        ?Request $request = null
    ): void {
        $config = $this->config();
        if (!$this->isEnabled($config)) {
            return;
        }

        $normalizedTicket = trim((string) $ticket);
        $normalizedRandstr = trim((string) $randstr);
        if ($normalizedTicket === '' || $normalizedRandstr === '') {
            throw new \RuntimeException('请先完成腾讯验证码校验后再登录。');
        }

        $context = [
            'captcha_app_id' => (string) ($config['app_id'] ?? ''),
            'has_secret_id' => trim((string) ($config['secret_id'] ?? '')) !== '',
            'has_secret_key' => trim((string) ($config['secret_key'] ?? '')) !== '',
            'ticket_prefix' => substr($normalizedTicket, 0, 12),
            'randstr' => $normalizedRandstr,
            'user_ip' => $userIp,
        ];

        $secretId = trim((string) ($config['secret_id'] ?? ''));
        $secretKey = trim((string) ($config['secret_key'] ?? ''));
        if ($secretId === '' || $secretKey === '') {
            $this->logger->warning('captcha', '腾讯验证码缺少腾讯云密钥，跳过后端校验', $context, null, $request);
            return;
        }

        $payload = [
            'CaptchaType' => 9,
            'Ticket' => $normalizedTicket,
            'Randstr' => $normalizedRandstr,
            'CaptchaAppId' => (int) ($config['app_id'] ?? 0),
            'AppSecretKey' => (string) ($config['app_secret_key'] ?? ''),
        ];
        if ($userIp !== '') {
            $payload['UserIp'] = $userIp;
        }

        try {
            $response = $this->describeCaptchaResult($payload, $secretId, $secretKey);
        } catch (\Throwable $throwable) {
            $this->logger->error('captcha', '腾讯验证码校验请求失败', $context + [
                'exception' => $throwable->getMessage(),
            ], null, $request);
            throw new \RuntimeException('验证码服务暂时不可用，请稍后重试。');
        }

        $result = $response['Response'] ?? [];
        $captchaCode = (int) ($result['CaptchaCode'] ?? 0);
        $captchaMsg = (string) ($result['CaptchaMsg'] ?? '');
        $isSuccess = $captchaCode === 1;

        if (!$isSuccess) {
            $this->logger->warning('captcha', '腾讯验证码校验未通过', $context + [
                'captcha_code' => $captchaCode,
                'captcha_message' => $captchaMsg,
            ], null, $request);
            throw new \RuntimeException('验证码校验失败，请重试。');
        }

        $this->logger->info('captcha', '腾讯验证码校验通过', $context + [
            'captcha_code' => $captchaCode,
        ], null, $request);
    }

    private function describeCaptchaResult(array $payload, string $secretId, string $secretKey): array
    {
        $service = 'captcha';
        $host = 'captcha.tencentcloudapi.com';
        $version = '2019-07-22';
        $action = 'DescribeCaptchaResult';
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);
        $encodedPayload = json_encode_unicode($payload);
        if ($encodedPayload === false) {
            throw new \RuntimeException('验证码请求参数编码失败。');
        }

        $algorithm = 'TC3-HMAC-SHA256';
        $credentialScope = $date . '/' . $service . '/tc3_request';
        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\nx-tc-action:" . strtolower($action) . "\n";
        $signedHeaders = 'content-type;host;x-tc-action';
        $hashedRequestPayload = hash('sha256', $encodedPayload);
        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            $hashedRequestPayload,
        ]);
        $stringToSign = implode("\n", [
            $algorithm,
            (string) $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);
        $authorization = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $algorithm,
            $secretId,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        return $this->httpJson('https://' . $host . '/', [
            'Authorization: ' . $authorization,
            'Content-Type: application/json; charset=utf-8',
            'Host: ' . $host,
            'X-TC-Action: ' . $action,
            'X-TC-Timestamp: ' . (string) $timestamp,
            'X-TC-Version: ' . $version,
        ], $encodedPayload);
    }

    private function httpJson(string $url, array $headers, string $payload): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $result = curl_exec($ch);
            if ($result === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException('验证码请求失败：' . $error);
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException('验证码服务返回异常状态码：' . $statusCode);
            }

            $decoded = json_decode((string) $result, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('验证码服务返回了无效响应。');
            }

            return $decoded;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 12,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            throw new \RuntimeException('验证码服务请求失败。');
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('验证码服务返回了无效响应。');
        }

        return $decoded;
    }

    private function defaults(): array
    {
        return [
            'trigger_failed_attempts' => '3',
            'app_id' => '',
            'app_secret_key' => '',
            'secret_id' => '',
            'secret_key' => '',
        ];
    }
}
