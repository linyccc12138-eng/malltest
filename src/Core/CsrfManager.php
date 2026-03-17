<?php

declare(strict_types=1);

namespace Mall\Core;

class CsrfManager
{
    public function __construct(private readonly SessionManager $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put('csrf_token', $token);
        }

        return $token;
    }

    public function validate(?string $token): bool
    {
        $current = $this->session->get('csrf_token');
        return is_string($current) && is_string($token) && hash_equals($current, $token);
    }
}
