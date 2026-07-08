<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Item;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Item\Item;
use NeneInvoice\Item\ItemListFilter;
use NeneInvoice\Item\ItemNotFoundException;
use NeneInvoice\Item\ItemSort;
use NeneInvoice\Item\PdoItemRepository;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PdoItemRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private PdoItemRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/items.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoItemRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->orgId, new FixedClock());
    }

    public function test_save_then_find_round_trips(): void
    {
        $id = $this->repository->save(new Item(organizationId: 1, description: '保守サポート', defaultUnitPriceCents: 50000, defaultTaxRateBps: 1000));

        $item = $this->repository->findById($id);
        self::assertNotNull($item);
        self::assertSame('保守サポート', $item->description);
        self::assertSame(50000, $item->defaultUnitPriceCents);
        self::assertSame(1000, $item->defaultTaxRateBps);
    }

    public function test_reads_are_scoped_to_the_request_org(): void
    {
        $this->repository->save(new Item(organizationId: 1, description: 'Mine', defaultUnitPriceCents: 1000, defaultTaxRateBps: 1000));

        $this->orgId->set(2);
        self::assertSame(0, $this->repository->countForAdminList(new ItemListFilter()));
        self::assertCount(0, $this->repository->findAll(20, 0));
    }

    public function test_update_changes_fields(): void
    {
        $id = $this->repository->save(new Item(organizationId: 1, description: 'Before', defaultUnitPriceCents: 1000, defaultTaxRateBps: 1000));

        $existing = $this->repository->findById($id);
        self::assertNotNull($existing);
        $this->repository->update(new Item(
            organizationId: 1,
            description: 'After',
            defaultUnitPriceCents: 2500,
            defaultTaxRateBps: 800,
            id: $id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        ));

        $updated = $this->repository->findById($id);
        self::assertNotNull($updated);
        self::assertSame('After', $updated->description);
        self::assertSame(2500, $updated->defaultUnitPriceCents);
        self::assertSame(800, $updated->defaultTaxRateBps);
    }

    public function test_delete_is_soft_and_hides_the_row(): void
    {
        $id = $this->repository->save(new Item(organizationId: 1, description: 'Doomed', defaultUnitPriceCents: 1000, defaultTaxRateBps: 1000));

        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->countForAdminList(new ItemListFilter()));
    }

    public function test_delete_missing_throws(): void
    {
        $this->expectException(ItemNotFoundException::class);
        $this->repository->delete(999);
    }

    public function test_admin_list_searches_description_and_sorts_by_price(): void
    {
        $this->repository->save(new Item(organizationId: 1, description: 'Web制作', defaultUnitPriceCents: 30000, defaultTaxRateBps: 1000));
        $this->repository->save(new Item(organizationId: 1, description: '保守サポート', defaultUnitPriceCents: 50000, defaultTaxRateBps: 1000));
        $this->repository->save(new Item(organizationId: 1, description: '保守 追加', defaultUnitPriceCents: 10000, defaultTaxRateBps: 800));

        $filter = new ItemListFilter('保守');
        self::assertSame(2, $this->repository->countForAdminList($filter));

        $rows = $this->repository->findForAdminList($filter, ItemSort::fromInput('unit_price', 'asc'), 20, 0);
        $prices = array_map(static fn (Item $i): int => $i->defaultUnitPriceCents, $rows);
        self::assertSame([10000, 50000], $prices);
    }
}
