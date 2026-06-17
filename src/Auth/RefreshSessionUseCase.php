<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Auth\TokenIssuerInterface;
use NeneInvoice\User\UserRepositoryInterface;

/**
 * Silent re-authentication via the refresh-token cookie (ADR 0014).
 *
 * Validates the presented token, rotates it within its family, and re-mints a
 * short-lived access token scoped to the SAME user and organization (no
 * cross-tenant escalation — ADR 0006). Presenting an already-spent or revoked
 * token revokes the entire family (reuse defense).
 */
final readonly class RefreshSessionUseCase implements RefreshSessionUseCaseInterface
{
    /** Mirrors {@see LoginUseCase}: access tokens stay short-lived and in-memory. */
    private const ACCESS_TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokens,
        private UserRepositoryInterface $users,
        private RefreshTokenIssuer $issuer,
        private TokenIssuerInterface $tokenIssuer,
    ) {
    }

    public function execute(string $rawToken): RefreshedSession
    {
        $now = date('Y-m-d H:i:s');
        $record = $this->refreshTokens->findByHash(RefreshTokenSecret::hash($rawToken));

        if ($record === null) {
            throw new InvalidRefreshTokenException();
        }

        // Replay: a token that was already rotated away (or revoked) is presented
        // again → assume theft and burn the whole lineage.
        if ($record->isConsumed()) {
            $this->refreshTokens->revokeFamily($record->familyId, $now);

            throw new RefreshTokenReuseException();
        }

        if ($record->isExpired($now)) {
            throw new InvalidRefreshTokenException();
        }

        // Re-read the principal: the session must end if the user was deactivated
        // or moved tenant since the token was issued.
        $user = $this->users->findById($record->userId);

        if (
            $user === null
            || $user->id === null
            || $user->status !== 'active'
            || $user->organizationId !== $record->organizationId
        ) {
            $this->refreshTokens->revokeFamily($record->familyId, $now);

            throw new InvalidRefreshTokenException();
        }

        // Rotate: spend the presented token, then mint its successor in the family.
        $this->refreshTokens->markUsed($record->id ?? 0, $now);
        $rotated = $this->issuer->issue($user->id, $user->organizationId, $record->familyId);

        $issuedAt = time();
        $accessToken = $this->tokenIssuer->issue([
            'sub' => $user->id,
            'role' => $user->role->value,
            'org' => $user->organizationId,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::ACCESS_TOKEN_TTL_SECONDS,
        ]);

        return new RefreshedSession($accessToken, $rotated);
    }
}
