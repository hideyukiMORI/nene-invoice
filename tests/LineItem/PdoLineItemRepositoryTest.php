<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\LineItem;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\PdoLineItemRepository;
use NeneInvoice\Tests\Support\FixedClock;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoLineItemRepositoryTest extends TestCase
{
    private PdoLineItemRepository $repository;
    private PDO $pdo;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    protected function setUp(): void
    {
        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: ':memory:',
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $factory = new PdoConnectionFactory($config);
        $this->pdo = $factory->create();

        foreach (['line_items', 'invoices', 'quotes'] as $table) {
            $schema = file_get_contents(dirname(__DIR__, 2) . "/database/schema/{$table}.sql");
            self::assertIsString($schema);
            $this->pdo->exec($schema);
        }

        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoLineItemRepository(new PdoDatabaseQueryExecutor($factory, $this->pdo), $this->orgId, new FixedClock());
    }

    public function test_replace_then_find_returns_lines_in_sort_order(): void
    {
        $this->repository->replaceForParent(LineItemParent::Quote, 10, [
            new LineItem(LineItemParent::Quote, 10, 'Second', 1, 1000, 1000, sortOrder: 1),
            new LineItem(LineItemParent::Quote, 10, 'First', 2, 500, 800, sortOrder: 0),
        ]);

        $lines = $this->repository->findByParent(LineItemParent::Quote, 10);

        self::assertCount(2, $lines);
        self::assertSame('First', $lines[0]->description);
        self::assertSame('Second', $lines[1]->description);
        self::assertSame(1000, $lines[0]->lineSubtotalCents()); // 2 × 500
    }

    public function test_replace_clears_previous_lines(): void
    {
        $this->repository->replaceForParent(LineItemParent::Quote, 10, [
            new LineItem(LineItemParent::Quote, 10, 'Old A', 1, 1000, 1000),
            new LineItem(LineItemParent::Quote, 10, 'Old B', 1, 1000, 1000),
        ]);

        $this->repository->replaceForParent(LineItemParent::Quote, 10, [
            new LineItem(LineItemParent::Quote, 10, 'New only', 1, 2000, 1000),
        ]);

        $lines = $this->repository->findByParent(LineItemParent::Quote, 10);
        self::assertCount(1, $lines);
        self::assertSame('New only', $lines[0]->description);
    }

    public function test_lines_are_isolated_by_parent(): void
    {
        $this->repository->replaceForParent(LineItemParent::Quote, 10, [
            new LineItem(LineItemParent::Quote, 10, 'Quote line', 1, 1000, 1000),
        ]);
        $this->repository->replaceForParent(LineItemParent::Invoice, 10, [
            new LineItem(LineItemParent::Invoice, 10, 'Invoice line', 1, 1000, 1000),
        ]);

        // Same parent_id (10) but different type → independent.
        self::assertCount(1, $this->repository->findByParent(LineItemParent::Quote, 10));
        self::assertSame('Invoice line', $this->repository->findByParent(LineItemParent::Invoice, 10)[0]->description);
    }

    public function test_delete_for_parent_removes_all(): void
    {
        $this->repository->replaceForParent(LineItemParent::Quote, 10, [
            new LineItem(LineItemParent::Quote, 10, 'X', 1, 1000, 1000),
        ]);

        $this->repository->deleteForParent(LineItemParent::Quote, 10);

        self::assertCount(0, $this->repository->findByParent(LineItemParent::Quote, 10));
    }

    public function test_recent_for_organization_scopes_by_org_and_excludes_deleted(): void
    {
        // Org 1: one live invoice, one live quote, one soft-deleted invoice.
        $invoiceLive    = $this->insertInvoice(orgId: 1, isDeleted: false);
        $quoteLive      = $this->insertQuote(orgId: 1, isDeleted: false);
        $invoiceDeleted = $this->insertInvoice(orgId: 1, isDeleted: true);
        // Org 2: must never leak into org 1's suggestions.
        $invoiceOtherOrg = $this->insertInvoice(orgId: 2, isDeleted: false);

        $this->insertLineItem(LineItemParent::Invoice, $invoiceLive, 'Consulting', 12000, 1000, '2026-06-01 09:00:00');
        $this->insertLineItem(LineItemParent::Quote, $quoteLive, 'Design', 8000, 800, '2026-06-02 09:00:00');
        $this->insertLineItem(LineItemParent::Invoice, $invoiceDeleted, 'Hidden', 99999, 1000, '2026-06-03 09:00:00');
        $this->insertLineItem(LineItemParent::Invoice, $invoiceOtherOrg, 'Other org', 50000, 1000, '2026-06-04 09:00:00');

        $rows = $this->repository->recentForOrganization(100);

        $descriptions = array_map(static fn (array $r): string => $r['description'], $rows);
        self::assertSame(['Design', 'Consulting'], $descriptions); // newest first, no Hidden/Other org
        self::assertSame(8000, $rows[0]['unit_price_cents']);
        self::assertSame(800, $rows[0]['tax_rate_bps']);
    }

    private function insertInvoice(int $orgId, bool $isDeleted): int
    {
        $this->pdo->exec(sprintf(
            "INSERT INTO invoices (organization_id, client_id, status, is_deleted, created_at, updated_at)
             VALUES (%d, 1, 'draft', %d, '2026-06-01 00:00:00', '2026-06-01 00:00:00')",
            $orgId,
            $isDeleted ? 1 : 0,
        ));

        return (int) $this->pdo->lastInsertId();
    }

    private function insertQuote(int $orgId, bool $isDeleted): int
    {
        $this->pdo->exec(sprintf(
            "INSERT INTO quotes (organization_id, client_id, quote_number, status, is_deleted, created_at, updated_at)
             VALUES (%d, 1, 'Q-%d', 'draft', %d, '2026-06-01 00:00:00', '2026-06-01 00:00:00')",
            $orgId,
            random_int(1, 1_000_000),
            $isDeleted ? 1 : 0,
        ));

        return (int) $this->pdo->lastInsertId();
    }

    private function insertLineItem(
        LineItemParent $parentType,
        int $parentId,
        string $description,
        int $unitPriceCents,
        int $taxRateBps,
        string $createdAt,
    ): void {
        $this->pdo->exec(sprintf(
            "INSERT INTO line_items (parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order, created_at, updated_at)
             VALUES ('%s', %d, '%s', 1, %d, %d, 0, '%s', '%s')",
            $parentType->value,
            $parentId,
            $description,
            $unitPriceCents,
            $taxRateBps,
            $createdAt,
            $createdAt,
        ));
    }
}
