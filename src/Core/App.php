<?php

declare(strict_types=1);

namespace Mall\Core;

use Throwable;

class App
{
    private Config $config;
    private Router $router;
    private array $instances = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->router = new Router();
        date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Shanghai'));
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function instance(string $name, mixed $service): void
    {
        $this->instances[$name] = $service;
    }

    public function make(string $name): mixed
    {
        if (!array_key_exists($name, $this->instances)) {
            throw new \RuntimeException('未注册服务：' . $name);
        }

        return $this->instances[$name];
    }

    public function handle(): void
    {
        $request = Request::capture();

        try {
            $response = $this->router->dispatch($request, $this);
            $response->send();
        } catch (Throwable $throwable) {
            try {
                /** @var Logger $logger */
                $logger = $this->make('logger');
                $logger->error(
                    'system',
                    '系统发生未捕获异常',
                    [
                        'message' => $throwable->getMessage(),
                        'trace' => $this->config()->get('app.debug') ? $throwable->getTraceAsString() : '生产环境已隐藏调用栈',
                    ],
                    null,
                    $request
                );
            } catch (Throwable) {
                // 日志组件异常时继续输出错误响应。
            }

            $payload = [
                'message' => '系统繁忙，请稍后再试。',
            ];

            if ($this->config()->get('app.debug')) {
                $payload['debug'] = $throwable->getMessage();
            }

            if ($request->expectsJson()) {
                Response::json($payload, 500)->send();
                return;
            }

            $html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>系统异常</title></head><body style="font-family:Microsoft YaHei,PingFang SC,sans-serif;background:#f7efe3;color:#2f2419;padding:40px;"><h1>系统异常</h1><p>页面暂时无法访问，请稍后刷新重试。</p></body></html>';
            Response::html($html, 500)->send();
        }
    }
}