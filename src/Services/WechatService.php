<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\Logger;

class WechatService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly Logger $logger
    ) {
    }

    public function serviceAccountConfig(): array
    {
        return $this->settings->getGroup('wechat_service_account');
    }

    public function payConfig(): array
    {
        return $this->settings->getGroup('wechat_pay');
    }

    public function buildOauthUrl(string $redirectUri, string $state, string $scope = 'snsapi_base'): string
    {
        $config = $this->serviceAccountConfig();
        $appId = (string) ($config['app_id'] ?? '');
        if ($appId === '') {
            throw new \RuntimeException('公众号 AppID 未配置。');
        }

        $params = [
            'appid' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ];

        return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query($params) . '#wechat_redirect';
    }

    public function exchangeOauthCode(string $code): array
    {
        $config = $this->serviceAccountConfig();
        $appId = (string) ($config['app_id'] ?? '');
        $secret = (string) ($config['app_secret'] ?? '');
        if ($appId === '' || $secret === '') {
            throw new \RuntimeException('公众号 AppID 或 AppSecret 未配置。');
        }

        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
            'appid' => $appId,
            'secret' => $secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        return $this->httpJson('GET', $url);
    }

    public function createPayOrder(array $order, array $user): array
    {
        $config = $this->payConfig();
        $this->assertPayFields($config, ['app_id', 'merchant_id', 'merchant_serial_no', 'api_v3_key', 'private_key_content', 'notify_url']);

        $mode = strtoupper((string) ($config['pay_mode'] ?? 'JSAPI'));
        $payload = [
            'appid' => $config['app_id'],
            'mchid' => $config['merchant_id'],
            'description' => '奇妙集市订单 ' . ($order['order_no'] ?? ''),
            'out_trade_no' => $order['order_no'],
            'notify_url' => $config['notify_url'],
            'amount' => [
                'total' => (int) round(((float) $order['payable_amount']) * 100),
                'currency' => 'CNY',
            ],
        ];

        if ($mode === 'H5') {
            $endpoint = 'https://api.mch.weixin.qq.com/v3/pay/transactions/h5';
            $payload['scene_info'] = [
                'payer_client_ip' => '127.0.0.1',
                'h5_info' => ['type' => 'Wap'],
            ];
        } else {
            if (empty($user['openid'])) {
                throw new \RuntimeException('JSAPI 支付要求用户已绑定微信 OpenID。');
            }
            $endpoint = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';
            $payload['payer'] = ['openid' => (string) $user['openid']];
        }

        $response = $this->wechatPayRequest('POST', $endpoint, $payload, $config);

        if ($mode === 'H5') {
            return [
                'mode' => 'H5',
                'message' => '微信 H5 支付参数创建成功。',
                'pay_url' => (string) ($response['h5_url'] ?? ''),
                'raw' => $response,
            ];
        }

        $prepayId = (string) ($response['prepay_id'] ?? '');
        if ($prepayId === '') {
            throw new \RuntimeException('未获取到 prepay_id。');
        }

        $timestamp = (string) time();
        $nonceStr = bin2hex(random_bytes(8));
        $package = 'prepay_id=' . $prepayId;
        $stringToSign = implode("\n", [$config['app_id'], $timestamp, $nonceStr, $package]) . "\n";
        $privateKey = openssl_pkey_get_private((string) $config['private_key_content']);
        if ($privateKey === false) {
            throw new \RuntimeException('商户私钥内容无效。');
        }
        openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return [
            'mode' => 'JSAPI',
            'message' => '微信 JSAPI 支付参数创建成功。',
            'pay_params' => [
                'appId' => $config['app_id'],
                'timeStamp' => $timestamp,
                'nonceStr' => $nonceStr,
                'package' => $package,
                'signType' => 'RSA',
                'paySign' => base64_encode($signature),
            ],
            'raw' => $response,
        ];
    }

    public function handlePayNotify(array $headers, string $body): array
    {
        $config = $this->payConfig();
        $this->assertPayFields($config, ['api_v3_key', 'platform_cert_content']);
        $this->verifyNotifySignature($headers, $body, $config);

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('微信支付通知报文格式无效。');
        }

        $resource = $payload['resource'] ?? null;
        if (!is_array($resource)) {
            throw new \RuntimeException('微信支付通知缺少加密资源。');
        }

        $resourceData = $this->decryptNotifyResource($resource, (string) $config['api_v3_key']);
        $orderNo = (string) ($resourceData['out_trade_no'] ?? '');
        if ($orderNo === '') {
            throw new \RuntimeException('微信支付通知缺少订单号。');
        }

        return [
            'order_no' => $orderNo,
            'payload' => $resourceData,
            'raw' => $payload,
        ];
    }

    public function testPayConfig(array $config): array
    {
        $this->assertPayFields($config, ['app_id', 'merchant_id', 'merchant_serial_no', 'api_v3_key', 'private_key_content']);
        $response = $this->wechatPayRequest('GET', 'https://api.mch.weixin.qq.com/v3/certificates', null, $config);

        return [
            'success' => true,
            'message' => '微信支付配置测试成功。',
            'certificate_count' => count($response['data'] ?? []),
        ];
    }

    public function testServiceAccount(array $config): array
    {
        if (empty($config['app_id']) || empty($config['app_secret'])) {
            throw new \RuntimeException('公众号 AppID 或 AppSecret 未配置。');
        }

        $result = $this->fetchAccessToken((string) $config['app_id'], (string) $config['app_secret']);
        if (empty($result['access_token'])) {
            throw new \RuntimeException('未获取到公众号 access_token。');
        }

        return [
            'success' => true,
            'message' => '微信公众号配置测试成功。',
            'token_preview' => substr((string) $result['access_token'], 0, 16) . '...',
        ];
    }

    public function sendTemplateMessage(string $openid, string $templateId, array $data, ?string $url = null): array
    {
        if ($openid === '' || $templateId === '') {
            return ['success' => false, 'message' => '缺少 OpenID 或模板 ID。'];
        }

        $config = $this->serviceAccountConfig();
        $tokenResult = $this->fetchAccessToken((string) ($config['app_id'] ?? ''), (string) ($config['app_secret'] ?? ''));
        $accessToken = (string) ($tokenResult['access_token'] ?? '');
        if ($accessToken === '') {
            return ['success' => false, 'message' => '未获取到 access_token。'];
        }

        $payload = [
            'touser' => $openid,
            'template_id' => $templateId,
            'url' => $url,
            'data' => $data,
        ];

        $response = $this->httpJson(
            'POST',
            'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $accessToken,
            $payload,
            ['Content-Type: application/json']
        );

        $success = (int) ($response['errcode'] ?? -1) === 0;
        if (!$success) {
            $this->logger->warning('wechat', '微信公众号模板消息发送失败', $response);
        }

        return [
            'success' => $success,
            'response' => $response,
        ];
    }

    private function fetchAccessToken(string $appId, string $secret): array
    {
        if ($appId === '' || $secret === '') {
            throw new \RuntimeException('公众号 AppID 或 AppSecret 未配置。');
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/token?' . http_build_query([
            'grant_type' => 'client_credential',
            'appid' => $appId,
            'secret' => $secret,
        ]);

        return $this->httpJson('GET', $url);
    }

    private function verifyNotifySignature(array $headers, string $body, array $config): void
    {
        $timestamp = $this->headerValue($headers, ['HTTP_WECHATPAY_TIMESTAMP', 'Wechatpay-Timestamp', 'wechatpay-timestamp']);
        $nonce = $this->headerValue($headers, ['HTTP_WECHATPAY_NONCE', 'Wechatpay-Nonce', 'wechatpay-nonce']);
        $signature = $this->headerValue($headers, ['HTTP_WECHATPAY_SIGNATURE', 'Wechatpay-Signature', 'wechatpay-signature']);

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            throw new \RuntimeException('微信支付通知验签头缺失。');
        }

        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $publicKey = $this->publicKeyFromCertificate((string) $config['platform_cert_content']);
        if ($publicKey === false) {
            throw new \RuntimeException('微信支付平台证书内容无效。');
        }

        $verified = openssl_verify($message, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new \RuntimeException('微信支付通知验签失败。');
        }
    }

    private function decryptNotifyResource(array $resource, string $apiV3Key): array
    {
        $ciphertext = base64_decode((string) ($resource['ciphertext'] ?? ''), true);
        $nonce = (string) ($resource['nonce'] ?? '');
        $aad = (string) ($resource['associated_data'] ?? '');
        if ($ciphertext === false || $nonce === '') {
            throw new \RuntimeException('微信支付通知密文无效。');
        }

        $tag = substr($ciphertext, -16);
        $encrypted = substr($ciphertext, 0, -16);
        $plaintext = openssl_decrypt($encrypted, 'aes-256-gcm', $apiV3Key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
        if ($plaintext === false) {
            throw new \RuntimeException('微信支付通知解密失败。');
        }

        $decoded = json_decode($plaintext, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('微信支付通知解密结果无效。');
        }

        return $decoded;
    }

    private function publicKeyFromCertificate(string $certificateContent): mixed
    {
        $publicKey = openssl_pkey_get_public($certificateContent);
        if ($publicKey !== false) {
            return $publicKey;
        }

        $cert = openssl_x509_read($certificateContent);
        if ($cert === false) {
            return false;
        }

        return openssl_pkey_get_public($cert);
    }

    private function assertPayFields(array $config, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($config[$field])) {
                throw new \RuntimeException('缺少微信支付配置项：' . $field);
            }
        }
    }

    private function headerValue(array $headers, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (isset($headers[$candidate]) && $headers[$candidate] !== '') {
                return (string) $headers[$candidate];
            }
        }

        return '';
    }

    private function wechatPayRequest(string $method, string $url, ?array $payload, array $config): array
    {
        $body = $payload === null ? '' : json_encode_unicode($payload);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $urlParts = parse_url($url);
        $pathWithQuery = ($urlParts['path'] ?? '/') . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
        $message = implode("\n", [strtoupper($method), $pathWithQuery, $timestamp, $nonce, $body]) . "\n";

        $privateKey = openssl_pkey_get_private((string) $config['private_key_content']);
        if ($privateKey === false) {
            throw new \RuntimeException('商户私钥内容无效。');
        }
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $authorization = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            $config['merchant_id'],
            $nonce,
            base64_encode($signature),
            $timestamp,
            $config['merchant_serial_no']
        );

        $headers = [
            'Authorization: ' . $authorization,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: magic-mall/1.0',
        ];

        return $this->httpJson($method, $url, $payload, $headers);
    }

    private function httpJson(string $method, string $url, ?array $payload = null, array $headers = []): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode_unicode($payload));
            }

            $result = curl_exec($ch);
            if ($result === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException('微信接口请求失败：' . $error);
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $decoded = json_decode($result, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
            if ($statusCode >= 400) {
                throw new \RuntimeException('微信接口返回错误：' . ($decoded['message'] ?? $decoded['errmsg'] ?? $statusCode));
            }

            return $decoded;
        }

        $context = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload !== null ? json_encode_unicode($payload) : '',
                'ignore_errors' => true,
            ],
        ];
        $result = file_get_contents($url, false, stream_context_create($context));
        $decoded = json_decode((string) $result, true);
        return is_array($decoded) ? $decoded : [];
    }
}