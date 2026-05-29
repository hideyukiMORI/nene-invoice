<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\LineItem;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\PdoLineItemRepository;
use PHPUnit\Framework\TestCase;

final class PdoLineItemRepositoryTest extends TestCase
{
    private PdoLineItemRepository $repository;

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
        $pdo = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/line_items.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->repository = new PdoLineItemRepository(new PdoDatabaseQueryExecutor($factory, $pdo));
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
}
