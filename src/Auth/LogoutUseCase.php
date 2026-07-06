<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Http\ClockInterface;

/**
 * Server-side session revocation (ADR 0014).
 *
 * Logout MUST invalidate the refresh token server-side — clearing the in-memory
 * access token alone is not sufficient. Revoking the whole family also kills any
 * sibling token a thief may hold. Logout is idempotent and never fails: an
 * unknown/absent cookie is a no-op so the handler can always clear the cookie
 * and return success.
 */
final readonly class LogoutUseCase implements LogoutUseCaseInterface
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokens,
        private ClockInterface $clock,
    ) {
    }

    public function execute(?string $rawToken): void
    {
        if ($rawToken === null || $rawToken === '') {
            return;
        }

        $record = $this->refreshTokens->findByHash(RefreshTokenSecret::hash($rawToken));

        if ($record === null) {
            return;
        }

        $this->refreshTokens->revokeFamily($record->familyId, $this->clock->now()->format('Y-m-d H:i:s'));
    }
}
