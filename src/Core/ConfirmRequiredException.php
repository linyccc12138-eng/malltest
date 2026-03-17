<?php

declare(strict_types=1);

namespace Mall\Core;

class ConfirmRequiredException extends \RuntimeException
{
    public function __construct(string $message, private readonly array $payload = [])
    {
        parent::__construct($message);
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
