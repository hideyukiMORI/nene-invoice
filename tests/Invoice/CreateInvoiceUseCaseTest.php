<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Invoice\CreateInvoiceInput;
use NeneInvoice\Invoice\CreateInvoiceUseCase;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\InvoiceValidationException;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class CreateInvoiceUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private RecordingAuditRecorder $audit;
    private CreateInvoiceUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->clients = new InMemoryClientRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->useCase = new CreateInvoiceUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->invoices,
            fn () => $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            $this->audit,
            $this->holder,
        );
    }

    private function client(int $organizationId = 1): int
    {
        return $this->clients->save(new Client(organizationId: $organizationId, name: 'Buyer KK'));
    }

    public function test_creates_draft_invoice_with_totals_lines_and_audit(): void
    {
        $clientId = $this->client();

        $result = $this->useCase->execute(7, new CreateInvoiceInput(
            clientId: $clientId,
            lines: [
                new LineItemInput('Std', 1, 1000, 1000),
                new LineItemInput('Std2', 1, 1000, 1000),
            ],
        ));

        self::assertSame(InvoiceStatus::Draft, $result->invoice->status);
        self::assertNull($result->invoice->invoiceNumber); // numbered at issue
        self::assertNull($result->invoice->quoteId);
        self::assertSame(2000, $result->invoice->subtotalCents);
        self::assertSame(200, $result->invoice->taxCents);
        self::assertSame(2200, $result->invoice->totalCents);
        self::assertCount(2, $result->lines);
        self::assertSame(LineItemParent::Invoice, $result->lines[0]->parentType);
        self::assertSame('invoice.created', $this->audit->records[0]['action']);
    }

    public function test_cross_organization_client_is_rejected(): void
    {
        $clientId = $this->client(2);

        $this->expectException(InvoiceValidationException::class);
        $this->useCase->execute(7, new CreateInvoiceInput(
            clientId: $clientId,
            lines: [new LineItemInput('Std', 1, 1000, 1000)],
        ));
    }

    public function test_empty_line_items_rejected(): void
    {
        $clientId = $this->client();

        $this->expectException(InvoiceValidationException::class);
        $this->useCase->execute(7, new CreateInvoiceInput(clientId: $clientId, lines: []));
    }

    public function test_disallowed_tax_rate_rejected(): void
    {
        $clientId = $this->client();

        $this->expectException(InvoiceValidationException::class);
        $this->useCase->execute(7, new CreateInvoiceInput(
            clientId: $clientId,
            lines: [new LineItemInput('Std', 1, 1000, 500)],
        ));
    }
}
