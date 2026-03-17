<?php

declare(strict_types=1);

namespace Mall\Core;

class View
{
    public function __construct(private readonly string $viewPath)
    {
    }

    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, array_merge($data, ['content' => $content]));
    }

    private function renderFile(string $template, array $data): string
    {
        $file = rtrim($this->viewPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('视图文件不存在：' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}