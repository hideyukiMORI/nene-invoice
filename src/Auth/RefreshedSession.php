<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * Outcome of a successful silent refresh: a fresh in-memory access token plus
 * the rotated refresh token to re-seat in the cookie.
 */
final readonly class RefreshedSession
{
    public function __construct(
        public string $accessToken,
        public IssuedRefreshToken $refreshToken,
    ) {
    }
}
