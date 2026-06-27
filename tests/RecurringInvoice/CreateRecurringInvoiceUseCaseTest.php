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
use NeneInvoice\RecurringInvoice\RecurringFrequency;
use NeneInvoice\RecurringInvoice\RecurringInvoiceValidationException;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryRecurringInvoiceRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class CreateRecurringInvoiceUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryRecurringInvoiceRepository $recurring;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private RecordingAuditRecorder $audit;
    private CreateRecurringInvoiceUseCase $useCase;
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

        $this->useCase = new CreateRecurringInvoiceUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            fn () => $this->audit,
            $this->holder,
        );
    }

    /** @param list<LineItemInput>|null $lines */
    private function input(?int $clientId = null, ?array $lines = null, string $firstRunOn = '2026-07-01', string $name = '月次顧問料'): CreateRecurringInvoiceInput
    {
        return new CreateRecurringInvoiceInput(
            clientId: $clientId ?? $this->clientId,
            name: $name,
            frequency: RecurringFrequency::Monthly,
            firstRunOn: $firstRunOn,
            lines: $lines ?? [new LineItemInput('コンサル', 1, 10000, 1000), new LineItemInput('軽減', 1, 5000, 800)],
        );
    }

    public function test_creates_schedule_with_totals_lines_and_audit(): void
    {
        $result = $this->useCase->execute(7, $this->input());

        self::assertSame('月次顧問料', $result->schedule->name);
        self::assertSame(RecurringFrequency::Monthly, $result->schedule->frequency);
        self::assertSame(15000, $result->schedule->subtotalCents);
        self::assertSame(1400, $result->schedule->taxCents);
        self::assertSame(16400, $result->schedule->totalCents);
        self::assertSame('2026-07-01', $result->schedule->nextRunOn);
        self::assertTrue($result->schedule->isActive);
        self::assertCount(2, $result->lines);
        self::assertSame(LineItemParent::RecurringInvoice, $result->lines[0]->parentType);
        self::assertSame('recurring_invoice.created', $this->audit->records[0]['action']);
    }

    public function test_rejects_unknown_client(): void
    {
        $this->expectException(RecurringInvoiceValidationException::class);
        $this->useCase->execute(7, $this->input(clientId: 999));
    }

    public function test_rejects_cross_org_client(): void
    {
        $other = $this->clients->save(new Client(organizationId: 2, name: '他社'));

        $this->expectException(RecurringInvoiceValidationException::class);
        $this->useCase->execute(7, $this->input(clientId: $other));
    }

    public function test_rejects_empty_lines(): void
    {
        $this->expectException(RecurringInvoiceValidationException::class);
        $this->useCase->execute(7, $this->input(lines: []));
    }

    public function test_rejects_empty_name(): void
    {
        $this->expectException(RecurringInvoiceValidationException::class);
        $this->useCase->execute(7, $this->input(name: '  '));
    }

    public function test_rejects_disallowed_tax_rate(): void
    {
        $this->expectException(RecurringInvoiceValidationException::class);
        $this->useCase->execute(7, $this->input(lines: [new LineItemInput('x', 1, 1000, 500)]));
    }

    public function test_rejects_malformed_first_run_date(): void
    {
        $this->expectException(RecurringInvoiceValidationException::class);
        $this->useCase->execute(7, $this->input(firstRunOn: '2026-13-40'));
    }
}
