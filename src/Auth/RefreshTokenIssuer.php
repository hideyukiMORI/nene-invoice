<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Http\ClockInterface;

/**
 * Mints and persists a new refresh token for a principal (ADR 0014).
 *
 * Used both at login (a fresh family) and on rotation (a new token within the
 * existing family). Remember-me / variable lifetime is a follow-up (issue #464);
 * for now the lifetime is a single fixed default.
 */
final readonly class RefreshTokenIssuer
{
    /**
     * Absolute lifetime of a refresh token. Until remember-me lands (#464) this
     * is the single default applied to every session.
     */
    public const TOKEN_TTL_SECONDS = 14 * 24 * 60 * 60;

    public function __construct(
        private RefreshTokenRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Issues a refresh token, starting a new family when $familyId is null
     * (login) or extending an existing family on rotation.
     */
    public function issue(int $userId, ?int $organizationId, ?string $familyId = null): IssuedRefreshToken
    {
        $now = $this->clock->now()->getTimestamp();
        $expiresAtTs = $now + self::TOKEN_TTL_SECONDS;

        $raw = RefreshTokenSecret::generateRaw();

        $this->repository->create(new RefreshToken(
            userId: $userId,
            organizationId: $organizationId,
            familyId: $familyId ?? RefreshTokenSecret::generateFamilyId(),
            tokenHash: RefreshTokenSecret::hash($raw),
            issuedAt: date('Y-m-d H:i:s', $now),
            expiresAt: date('Y-m-d H:i:s', $expiresAtTs),
        ));

        return new IssuedRefreshToken($raw, date('Y-m-d H:i:s', $expiresAtTs), $expiresAtTs);
    }
}
