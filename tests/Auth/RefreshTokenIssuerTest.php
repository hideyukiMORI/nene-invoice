<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Auth\RefreshTokenSecret;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\InMemoryRefreshTokenRepository;
use PHPUnit\Framework\TestCase;

/**
 * Proves the Clock sweep: with an injected {@see FixedClock} the minted
 * issued-at / expires-at instants are fully deterministic (no real-time drift),
 * so refresh-token lifetime math can be asserted to the second.
 */
final class RefreshTokenIssuerTest extends TestCase
{
    public function test_fixed_clock_makes_issued_and_expiry_instants_deterministic(): void
    {
        $repository = new InMemoryRefreshTokenRepository();
        // Fixed UTC instant 2026-06-06 03:00:00 (default of FixedClock).
        $issuer = new RefreshTokenIssuer($repository, new FixedClock());

        $issued = $issuer->issue(userId: 42, organizationId: 7);

        // expiry = fixed now + 14d, computed from the clock, not wall time.
        self::assertSame('2026-06-20 03:00:00', $issued->expiresAt);
        self::assertSame(1781924400, $issued->expiresAtTimestamp);

        // The persisted record's issued-at is the fixed instant.
        $record = $repository->findByHash(RefreshTokenSecret::hash($issued->rawToken));
        self::assertNotNull($record);
        self::assertSame('2026-06-06 03:00:00', $record->issuedAt);
        self::assertSame('2026-06-20 03:00:00', $record->expiresAt);

        // Re-issuing under the same fixed clock yields identical timestamps:
        // the determinism the sweep buys (previously time()-dependent).
        $again = $issuer->issue(userId: 42, organizationId: 7);
        self::assertSame($issued->expiresAt, $again->expiresAt);
        self::assertSame($issued->expiresAtTimestamp, $again->expiresAtTimestamp);
    }
}
