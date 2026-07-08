<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Integration;

use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Auth\PdoRefreshTokenRepository;
use NeneInvoice\Auth\RefreshToken;
use NeneInvoice\Auth\RefreshTokenSecret;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see PdoRefreshTokenRepository} against the real production engines
 * (MySQL + PostgreSQL — issue #396): hash lookup, rotation (`markUsed`), family
 * revocation, and the `IS NULL` predicates. PostgreSQL is the strict case for the
 * nullable `organization_id` / `used_at` / `revoked_at` columns.
 *
 * Skips when the adapter is not configured, so the default SQLite `composer test`
 * is unaffected. CI applies the schema via `phinx migrate` first.
 */
#[Group('integration')]
final class RefreshTokenRepositoryDialectTest extends TestCase
{
    /**
     * @return iterable<string, array{0: callable(): ?PdoDatabaseQueryExecutor}>
     */
    public static function adapters(): iterable
    {
        yield 'postgresql' => [static fn (): ?PdoDatabaseQueryExecutor => IntegrationDatabase::pgsql()];
        yield 'mysql' => [static fn (): ?PdoDatabaseQueryExecutor => IntegrationDatabase::mysql()];
    }

    /**
     * @param callable(): ?PdoDatabaseQueryExecutor $connect
     */
    #[DataProvider('adapters')]
    public function test_rotation_and_family_revocation_round_trip(callable $connect): void
    {
        $db = $connect();

        if ($db === null) {
            self::markTestSkipped('Integration DB not configured for this adapter.');
        }

        $userId = random_int(1_000_000, 9_999_999);
        $familyId = RefreshTokenSecret::generateFamilyId();
        $repo = new PdoRefreshTokenRepository($db, new FixedClock());

        try {
            $rawA = RefreshTokenSecret::generateRaw();
            $idA = $repo->create($this->token($userId, $familyId, $rawA));

            // Hash lookup returns the row; org/used/revoked nullables come back null.
            $found = $repo->findByHash(RefreshTokenSecret::hash($rawA));
            self::assertNotNull($found);
            self::assertSame($userId, $found->userId);
            self::assertNull($found->organizationId);
            self::assertFalse($found->isConsumed());

            // Rotate: spend A, then add B in the same family.
            $repo->markUsed($idA, '2026-06-17 00:00:00');
            $spentA = $repo->findByHash(RefreshTokenSecret::hash($rawA));
            self::assertNotNull($spentA);
            self::assertTrue($spentA->isConsumed());

            $rawB = RefreshTokenSecret::generateRaw();
            $repo->create($this->token($userId, $familyId, $rawB));

            // Revoking the family kills every still-live token (B), and is keyed by family.
            $repo->revokeFamily($familyId, '2026-06-17 00:01:00');
            $revokedB = $repo->findByHash(RefreshTokenSecret::hash($rawB));
            self::assertNotNull($revokedB);
            self::assertNotNull($revokedB->revokedAt);
        } finally {
            $db->execute('DELETE FROM refresh_tokens WHERE user_id = ?', [$userId]);
        }
    }

    private function token(int $userId, string $familyId, string $rawToken): RefreshToken
    {
        return new RefreshToken(
            userId: $userId,
            organizationId: null,
            familyId: $familyId,
            tokenHash: RefreshTokenSecret::hash($rawToken),
            issuedAt: '2026-06-17 00:00:00',
            expiresAt: '2026-07-01 00:00:00',
        );
    }
}
