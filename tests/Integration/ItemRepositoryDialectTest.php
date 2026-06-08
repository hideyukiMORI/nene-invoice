<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Integration;

use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Item\Item;
use NeneInvoice\Item\ItemListFilter;
use NeneInvoice\Item\ItemSort;
use NeneInvoice\Item\PdoItemRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the dialect-portable repository SQL on the real production engines
 * (MySQL + PostgreSQL — issue #396): the boolean soft-delete predicates
 * (`is_deleted = FALSE/TRUE`) and the case-insensitive search
 * (`LOWER(col) LIKE LOWER(?)`). PostgreSQL is the strict case — it rejects
 * `boolean = integer` — so this is what proves the fix on a real engine.
 *
 * Skips when the adapter is not configured (no `<ADAPTER>_TEST_HOST` / driver),
 * so the default SQLite `composer test` is unaffected. CI applies the schema via
 * `phinx migrate` before running `composer test:integration`.
 */
#[Group('integration')]
final class ItemRepositoryDialectTest extends TestCase
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
    public function test_soft_delete_and_case_insensitive_search_round_trip(callable $connect): void
    {
        $db = $connect();

        if ($db === null) {
            self::markTestSkipped('Integration DB not configured for this adapter.');
        }

        // Isolate this run in its own organization so the suite is repeatable.
        $organizationId = random_int(1_000_000, 9_999_999);
        $orgHolder = new RequestScopedHolder();
        $orgHolder->set($organizationId);
        $repo = new PdoItemRepository($db, $orgHolder);

        try {
            // INSERT writes the inline boolean literal (is_deleted = FALSE).
            $id = $repo->save(new Item(
                organizationId: $organizationId,
                description: 'Mixed Case Maintenance',
                defaultUnitPriceCents: 50_000,
                defaultTaxRateBps: 1_000,
            ));

            // SELECT … WHERE is_deleted = FALSE
            self::assertNotNull($repo->findById($id));
            self::assertSame(1, $repo->countForAdminList(new ItemListFilter()));

            // LOWER(description) LIKE LOWER(?) — lowercase query matches mixed case.
            $hits = $repo->findForAdminList(new ItemListFilter(search: 'maintenance'), new ItemSort(), 20, 0);
            self::assertCount(1, $hits);

            // UPDATE … SET is_deleted = TRUE … WHERE … is_deleted = FALSE
            $repo->delete($id);
            self::assertNull($repo->findById($id));
            self::assertSame(0, $repo->countForAdminList(new ItemListFilter()));
        } finally {
            $db->execute('DELETE FROM items WHERE organization_id = ?', [$organizationId]);
        }
    }
}
