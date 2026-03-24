<?php

declare(strict_types=1);

namespace Mall\Core;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = '',
        private readonly array $data = [],
        int $status = 400
    ) {
        parent::__construct($message, $status);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function status(): int
    {
        $status = (int) $this->getCode();
        if ($status < 400 || $status > 599) {
            return 400;
        }

        return $status;
    }
}
