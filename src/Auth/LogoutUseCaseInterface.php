<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

interface LogoutUseCaseInterface
{
    /** Idempotently revokes the family of the presented refresh token, if any. */
    public function execute(?string $rawToken): void;
}
