<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

final readonly class LoginOutput
{
    public function __construct(
        public string $token,
    ) {
    }
}
