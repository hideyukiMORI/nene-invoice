<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Item;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Item\CreateItemInput;
use NeneInvoice\Item\CreateItemUseCase;
use NeneInvoice\Item\DeleteItemUseCase;
use NeneInvoice\Item\GetItemByIdUseCase;
use NeneInvoice\Item\Item;
use NeneInvoice\Item\ItemNotFoundException;
use NeneInvoice\Item\UpdateItemInput;
use NeneInvoice\Item\UpdateItemUseCase;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryItemRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ItemWriteUseCasesTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryItemRepository $repo;
    private RecordingAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new InMemoryItemRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
    }

    public function test_create_forces_resolved_organization_and_audits(): void
    {
        $this->holder->set(7);

        $item = (new CreateItemUseCase(new ImmediateTransactionManager(), fn () => $this->repo, fn () => $this->audit, $this->holder))
            ->execute(42, new CreateItemInput(description: '保守', defaultUnitPriceCents: 50000, defaultTaxRateBps: 1000));

        self::assertSame(7, $item->organizationId);
        self::assertSame(50000, $item->defaultUnitPriceCents);

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame('item.created', $record['action']);
        self::assertSame(42, $record['actor_user_id']);
        self::assertSame(7, $record['organization_id']);
        self::assertNull($record['before']);
        self::assertSame('保守', $record['after']['description'] ?? null);
    }

    public function test_get_blocks_cross_organization_target(): void
    {
        $otherOrg = $this->repo->save(new Item(organizationId: 2, description: 'Other', defaultUnitPriceCents: 1, defaultTaxRateBps: 1000));

        $this->expectException(ItemNotFoundException::class);
        (new GetItemByIdUseCase($this->repo))->execute($otherOrg);
    }

    public function test_update_records_before_and_after(): void
    {
        $id = $this->repo->save(new Item(organizationId: 1, description: 'Before', defaultUnitPriceCents: 1000, defaultTaxRateBps: 1000));

        (new UpdateItemUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, fn () => $this->audit, $this->holder))
            ->execute(9, $id, new UpdateItemInput(description: 'After', defaultUnitPriceCents: 2000, defaultTaxRateBps: 800));

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame('item.updated', $record['action']);
        self::assertSame('Before', $record['before']['description'] ?? null);
        self::assertSame('After', $record['after']['description'] ?? null);
        self::assertSame(800, $record['after']['default_tax_rate_bps'] ?? null);
    }

    public function test_update_blocks_cross_organization_target(): void
    {
        $otherOrg = $this->repo->save(new Item(organizationId: 2, description: 'Other', defaultUnitPriceCents: 1, defaultTaxRateBps: 1000));

        $this->expectException(ItemNotFoundException::class);
        (new UpdateItemUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, fn () => $this->audit, $this->holder))
            ->execute(1, $otherOrg, new UpdateItemInput(description: 'Hacked', defaultUnitPriceCents: 1, defaultTaxRateBps: 1000));
    }

    public function test_delete_soft_deletes_and_audits(): void
    {
        $id = $this->repo->save(new Item(organizationId: 1, description: 'Doomed', defaultUnitPriceCents: 1000, defaultTaxRateBps: 1000));

        (new DeleteItemUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, fn () => $this->audit, $this->holder))->execute(5, $id);

        self::assertNull($this->repo->findById($id));
        self::assertSame('item.deleted', $this->audit->records[0]['action']);
    }
}
