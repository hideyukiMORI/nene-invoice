<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

/**
 * Checks a presented service token against the registry at request time.
 *
 * Unlike {@see ServiceTokenRepositoryInterface}, lookups here are **not**
 * org-scoped: the `jti` is globally unique and the request org is derived from
 * the token itself, so revocation must be enforceable before any org scoping is
 * trusted. Tokens issued before the registry existed carry no `jti` and are not
 * checked here (the JWT signature + `exp` remain authoritative for them).
 */
interface ServiceTokenAuthorizerInterface
{
    /**
     * True when a non-revoked registry row exists for the `jti`. Returns false
     * for an unknown or revoked `jti` (fail-closed).
     */
    public function isActive(string $jti): bool;
}
