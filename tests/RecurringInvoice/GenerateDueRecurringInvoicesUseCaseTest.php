<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\RecurringInvoice\GenerateDueRecurringInvoicesUseCase;
use NeneInvoice\RecurringInvoice\RecurringFrequency;
use NeneInvoice\RecurringInvoice\RecurringInvoice;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryRecurringInvoiceRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class GenerateDueRecurringInvoicesUseCaseTest extends TestCase
{
    // FixedClock JST date.
    private const TODAY = '2026-06-06';

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryRecurringInvoiceRepository $recurring;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private InMemoryInvoiceRepository $invoices;
    private RecordingAuditRecorder $audit;
    private GenerateDueRecurringInvoicesUseCase $useCase;
    private int $clientId;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->recurring = new InMemoryRecurringInvoiceRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->clients = new InMemoryClientRepository($this->holder);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->clientId = $this->clients->save(new Client(organizationId: 1, name: '顧問先 株式会社'));

        $this->useCase = new GenerateDueRecurringInvoicesUseCase(
            $this->recurring,
            $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->invoices,
            fn () => $this->lineItems,
            fn () => $this->audit,
            new FixedClock(),
            $this->holder,
        );
    }

    private function seed(string $nextRunOn, bool $active = true, bool $withLines = true, ?int $clientId = null): int
    {
        $id = $this->recurring->save(new RecurringInvoice(
            organizationId: 1,
            clientId: $clientId ?? $this->clientId,
            name: '月次顧問料',
            frequency: RecurringFrequency::Monthly,
            subtotalCents: 15000,
            taxCents: 1400,
            totalCents: 16400,
            nextRunOn: $nextRunOn,
            isActive: $active,
            notes: '今月分の顧問料です。',
        ));

        if ($withLines) {
            $this->lineItems->replaceForParent(LineItemParent::RecurringInvoice, $id, [
                new LineItem(LineItemParent::RecurringInvoice, $id, 'コンサルティング', 1, 10000, 1000, sortOrder: 0),
                new LineItem(LineItemParent::RecurringInvoice, $id, '軽減税率品', 1, 5000, 800, sortOrder: 1),
            ]);
        }

        return $id;
    }

    public function test_generates_draft_invoice_and_advances_schedule(): void
    {
        $id = $this->seed('2026-06-01');

        $result = $this->useCase->execute(7);

        self::assertSame(1, $result->count());
        $invoiceId = $result->generated[0]['invoice_id'];
        self::assertSame($id, $result->generated[0]['recurring_invoice_id']);

        $invoice = $this->invoices->findById($invoiceId);
        self::assertNotNull($invoice);
        self::assertSame(InvoiceStatus::Draft, $invoice->status);
        self::assertNull($invoice->invoiceNumber); // numbered only at issue
        self::assertSame(15000, $invoice->subtotalCents);
        self::assertSame(1400, $invoice->taxCents);
        self::assertSame(16400, $invoice->totalCents);
        self::assertCount(2, $this->lineItems->findByParent(LineItemParent::Invoice, $invoiceId));
        self::assertSame('invoice.created', $this->audit->records[0]['action']);

        $schedule = $this->recurring->findById($id);
        self::assertNotNull($schedule);
        self::assertSame('2026-07-01', $schedule->nextRunOn);
        self::assertSame(self::TODAY, $schedule->lastRunOn);
        self::assertTrue($schedule->isActive);
    }

    public function test_same_day_rerun_is_idempotent(): void
    {
        $this->seed('2026-06-01');

        self::assertSame(1, $this->useCase->execute(7)->count());
        self::assertSame(0, $this->useCase->execute(7)->count());
        self::assertSame(1, $this->invoices->count()); // no duplicate
    }

    public function test_future_schedule_is_not_generated(): void
    {
        $this->seed('2026-07-01');

        self::assertSame(0, $this->useCase->execute(7)->count());
        self::assertSame(0, $this->invoices->count());
    }

    public function test_inactive_schedule_is_not_generated(): void
    {
        $this->seed('2026-06-01', active: false);

        self::assertSame(0, $this->useCase->execute(7)->count());
    }

    public function test_schedule_without_line_template_is_skipped_and_untouched(): void
    {
        $id = $this->seed('2026-06-01', withLines: false);

        self::assertSame(0, $this->useCase->execute(7)->count());

        $schedule = $this->recurring->findById($id);
        self::assertNotNull($schedule);
        self::assertSame('2026-06-01', $schedule->nextRunOn); // not advanced
        self::assertNull($schedule->lastRunOn);
    }

    public function test_schedule_for_missing_client_is_skipped(): void
    {
        $this->seed('2026-06-01', clientId: 999); // no such client in org

        self::assertSame(0, $this->useCase->execute(7)->count());
        self::assertSame(0, $this->invoices->count());
    }

    public function test_overdue_schedule_advances_one_period_anchored_not_to_today(): void
    {
        $id = $this->seed('2026-04-10'); // overdue (before today)

        self::assertSame(1, $this->useCase->execute(7)->count());

        $schedule = $this->recurring->findById($id);
        self::assertNotNull($schedule);
        // Anchored on its own next_run_on, advances exactly one period — it does
        // not jump to the run date.
        self::assertSame('2026-05-10', $schedule->nextRunOn);
        self::assertSame(self::TODAY, $schedule->lastRunOn);
    }
}
