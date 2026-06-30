<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\RecurringInvoice\GenerateDueRecurringInvoicesUseCase;
use NeneInvoice\RecurringInvoice\RecurringFrequency;
use NeneInvoice\RecurringInvoice\RecurringInvoice;
use NeneInvoice\RecurringInvoice\RecurringRunThrottleInterface;
use NeneInvoice\RecurringInvoice\ThrottledRecurringDueRunner;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryRecurringInvoiceRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ThrottledRecurringDueRunnerTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryRecurringInvoiceRepository $recurring;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private InMemoryInvoiceRepository $invoices;
    private GenerateDueRecurringInvoicesUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->recurring = new InMemoryRecurringInvoiceRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->clients   = new InMemoryClientRepository($this->holder);
        $this->invoices  = new InMemoryInvoiceRepository($this->holder);

        $clientId = $this->clients->save(new Client(organizationId: 1, name: '顧問先 株式会社'));

        $id = $this->recurring->save(new RecurringInvoice(
            organizationId: 1,
            clientId: $clientId,
            name: '月次顧問料',
            frequency: RecurringFrequency::Monthly,
            subtotalCents: 10000,
            taxCents: 1000,
            totalCents: 11000,
            nextRunOn: '2026-06-01',
            isActive: true,
        ));
        $this->lineItems->replaceForParent(LineItemParent::RecurringInvoice, $id, [
            new LineItem(LineItemParent::RecurringInvoice, $id, 'コンサルティング', 1, 10000, 1000, sortOrder: 0),
        ]);

        $this->useCase = new GenerateDueRecurringInvoicesUseCase(
            $this->recurring,
            $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->invoices,
            fn () => $this->lineItems,
            fn () => new RecordingAuditRecorder(),
            new FixedClock(),
            $this->holder,
        );
    }

    public function test_runs_once_then_throttled_same_day(): void
    {
        $throttle = new class () implements RecurringRunThrottleInterface {
            /** @var array<string, bool> */
            public array $claimed = [];

            public function claim(int $organizationId, string $date): bool
            {
                $key = $organizationId . '@' . $date;
                if (isset($this->claimed[$key])) {
                    return false;
                }
                $this->claimed[$key] = true;

                return true;
            }
        };

        $runner = new ThrottledRecurringDueRunner($this->useCase, $throttle, new FixedClock(), $this->holder);

        $first = $runner->runForCurrentOrg();
        self::assertNotNull($first);
        self::assertSame(1, $first->count());
        self::assertSame(1, $this->invoices->count());

        // Second call on the same JST day is throttled — no run, no duplicate.
        self::assertNull($runner->runForCurrentOrg());
        self::assertSame(1, $this->invoices->count());
    }

    public function test_skips_when_no_org_resolved(): void
    {
        $throttle = new class () implements RecurringRunThrottleInterface {
            public int $calls = 0;

            public function claim(int $organizationId, string $date): bool
            {
                ++$this->calls;

                return true;
            }
        };

        $emptyHolder = new RequestScopedHolder();
        $runner      = new ThrottledRecurringDueRunner($this->useCase, $throttle, new FixedClock(), $emptyHolder);

        self::assertNull($runner->runForCurrentOrg());
        self::assertSame(0, $throttle->calls);
        self::assertSame(0, $this->invoices->count());
    }
}
