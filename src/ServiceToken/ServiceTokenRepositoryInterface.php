<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

/**
 * Persistence for the service-token registry. Every query is scoped to the
 * organization held in the request-scoped org holder (ADR 0006). The token
 * value is never persisted — only metadata + the `jti` revocation key.
 */
interface ServiceTokenRepositoryInterface
{
    public function save(ServiceToken $token): int;

    public function findById(int $id): ?ServiceToken;

    /** @return list<ServiceToken> newest first */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /**
     * Marks the token revoked at the given timestamp. No-op if already revoked.
     *
     * @throws ServiceTokenNotFoundException when no matching token exists in the org
     */
    public function revoke(int $id, string $revokedAt): void;
}
