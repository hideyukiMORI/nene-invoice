<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

interface RefreshSessionUseCaseInterface
{
    /**
     * @throws InvalidRefreshTokenException when the token is unknown/expired/ineligible
     * @throws RefreshTokenReuseException when an already-spent token is replayed
     */
    public function execute(string $rawToken): RefreshedSession;
}
