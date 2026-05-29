<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

final readonly class LoginInput
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
