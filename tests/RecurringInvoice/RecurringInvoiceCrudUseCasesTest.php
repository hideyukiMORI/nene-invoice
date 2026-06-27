<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\RecurringInvoice\CreateRecurringInvoiceInput;
use NeneInvoice\RecurringInvoice\CreateRecurringInvoiceUseCase;
use NeneInvoice\RecurringInvoice\DeleteRecurringInvoiceUseCase;
use NeneInvoice\RecurringInvoice\GetRecurringInvoiceByIdUseCase;
use NeneInvoice\RecurringInvoice\ListRecurringInvoicesUseCase;
use NeneInvoice\RecurringInvoice\RecurringFrequency;
use NeneInvoice\RecurringInvoice\RecurringInvoiceNotFoundException;
use NeneInvoice\RecurringInvoice\UpdateRecurringInvoiceInput;
use NeneInvoice\RecurringInvoice\UpdateRecurringInvoiceUseCase;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryRecurringInvoiceRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class RecurringInvoiceCrudUseCasesTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryRecurringInvoiceRepository $recurring;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private RecordingAuditRecorder $audit;
    private int $clientId;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->recurring = new InMemoryRecurringInvoiceRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->clients = new InMemoryClientRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->clientId = $this->clients->save(new Client(organizationId: 1, name: '顧問先'));
    }

    private function createUseCase(): CreateRecurringInvoiceUseCase
    {
        return new CreateRecurringInvoiceUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            fn () => $this->audit,
            $this->holder,
        );
    }

    private function seed(string $name = '月次顧問料', string $firstRunOn = '2026-07-01'): int
    {
        return $this->createUseCase()->execute(7, new CreateRecurringInvoiceInput(
            clientId: $this->clientId,
            name: $name,
            frequency: RecurringFrequency::Monthly,
            firstRunOn: $firstRunOn,
            lines: [new LineItemInput('コンサル', 1, 10000, 1000)],
        ))->schedule->id ?? 0;
    }

    public function test_list_returns_items_and_total_with_paging(): void
    {
        $this->seed('A');
        $this->seed('B');
        $this->seed('C');

        $useCase = new ListRecurringInvoicesUseCase($this->recurring);

        $page = $useCase->execute(2, 0);
        self::assertSame(3, $page->total);
        self::assertCount(2, $page->items);
        self::assertSame('C', $page->items[0]->name); // newest first
    }

    public function test_get_returns_schedule_with_lines(): void
    {
        $id = $this->seed();
        $useCase = new GetRecurringInvoiceByIdUseCase($this->recurring, $this->lineItems);

        $result = $useCase->execute($id);
        self::assertSame($id, $result->schedule->id);
        self::assertCount(1, $result->lines);
        self::assertSame(LineItemParent::RecurringInvoice, $result->lines[0]->parentType);
    }

    public function test_get_unknown_id_throws(): void
    {
        $useCase = new GetRecurringInvoiceByIdUseCase($this->recurring, $this->lineItems);

        $this->expectException(RecurringInvoiceNotFoundException::class);
        $useCase->execute(999);
    }

    public function test_update_replaces_lines_recomputes_totals_and_audits(): void
    {
        $id = $this->seed();
        $useCase = $this->updateUseCase();

        $result = $useCase->execute(7, $id, new UpdateRecurringInvoiceInput(
            clientId: $this->clientId,
            name: '改定 顧問料',
            frequency: RecurringFrequency::Quarterly,
            nextRunOn: '2026-09-01',
            lines: [new LineItemInput('コンサル', 2, 10000, 1000), new LineItemInput('軽減', 1, 5000, 800)],
            isActive: false,
        ));

        self::assertSame('改定 顧問料', $result->schedule->name);
        self::assertSame(RecurringFrequency::Quarterly, $result->schedule->frequency);
        self::assertSame('2026-09-01', $result->schedule->nextRunOn);
        self::assertFalse($result->schedule->isActive);
        self::assertSame(25000, $result->schedule->subtotalCents); // 2×10000 + 5000
        self::assertSame(2400, $result->schedule->taxCents);       // 2000 + 400
        self::assertCount(2, $result->lines);

        $actions = array_map(static fn ($r) => $r['action'], $this->audit->records);
        self::assertContains('recurring_invoice.updated', $actions);

        // The line template was replaced (2 rows now).
        self::assertCount(2, $this->lineItems->findByParent(LineItemParent::RecurringInvoice, $id));
    }

    public function test_update_unknown_id_throws(): void
    {
        $useCase = $this->updateUseCase();

        $this->expectException(RecurringInvoiceNotFoundException::class);
        $useCase->execute(7, 999, new UpdateRecurringInvoiceInput(
            clientId: $this->clientId,
            name: 'x',
            frequency: RecurringFrequency::Monthly,
            nextRunOn: '2026-07-01',
            lines: [new LineItemInput('x', 1, 1000, 1000)],
            isActive: true,
        ));
    }

    public function test_delete_soft_deletes_and_audits(): void
    {
        $id = $this->seed();
        $useCase = new DeleteRecurringInvoiceUseCase(
            $this->recurring,
            $this->lineItems,
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->audit,
            $this->holder,
        );

        $useCase->execute(7, $id);

        self::assertNull($this->recurring->findById($id));
        $actions = array_map(static fn ($r) => $r['action'], $this->audit->records);
        self::assertContains('recurring_invoice.deleted', $actions);
    }

    public function test_delete_unknown_id_throws(): void
    {
        $useCase = new DeleteRecurringInvoiceUseCase(
            $this->recurring,
            $this->lineItems,
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->audit,
            $this->holder,
        );

        $this->expectException(RecurringInvoiceNotFoundException::class);
        $useCase->execute(7, 999);
    }

    private function updateUseCase(): UpdateRecurringInvoiceUseCase
    {
        return new UpdateRecurringInvoiceUseCase(
            $this->recurring,
            $this->lineItems,
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            fn () => $this->audit,
            $this->holder,
        );
    }
}
