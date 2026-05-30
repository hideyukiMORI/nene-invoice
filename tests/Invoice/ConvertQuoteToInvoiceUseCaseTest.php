<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\ConvertQuoteToInvoiceUseCase;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Quote\QuoteStatus;
use NeneInvoice\Quote\QuoteValidationException;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryQuoteRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ConvertQuoteToInvoiceUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryQuoteRepository $quotes;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryLineItemRepository $lineItems;
    private RecordingAuditRecorder $audit;
    private ConvertQuoteToInvoiceUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->quotes = new InMemoryQuoteRepository($this->holder);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->audit = new RecordingAuditRecorder();
        $this->useCase = new ConvertQuoteToInvoiceUseCase($this->quotes, $this->invoices, $this->lineItems, $this->audit, $this->holder);
    }

    private function quote(QuoteStatus $status): int
    {
        $id = $this->quotes->save(new Quote(
            organizationId: 1,
            clientId: 5,
            quoteNumber: 'EST-2026-001',
            status: $status,
            subtotalCents: 2000,
            taxCents: 180,
            totalCents: 2180,
        ));

        $this->lineItems->replaceForParent(LineItemParent::Quote, $id, [
            new LineItem(LineItemParent::Quote, $id, 'Std', 1, 1000, 1000, sortOrder: 0),
            new LineItem(LineItemParent::Quote, $id, 'Red', 1, 1000, 800, sortOrder: 1),
        ]);

        return $id;
    }

    public function test_converts_accepted_quote_copying_totals_lines_and_audits(): void
    {
        $quoteId = $this->quote(QuoteStatus::Accepted);

        $result = $this->useCase->execute(7, $quoteId);

        self::assertSame(InvoiceStatus::Draft, $result->invoice->status);
        self::assertSame($quoteId, $result->invoice->quoteId);
        self::assertNull($result->invoice->invoiceNumber); // numbered at issue
        self::assertSame(2180, $result->invoice->totalCents);
        self::assertCount(2, $result->lines);
        self::assertSame(LineItemParent::Invoice, $result->lines[0]->parentType);

        self::assertSame('invoice.created', $this->audit->records[0]['action']);
    }

    public function test_rejects_non_accepted_quote(): void
    {
        $quoteId = $this->quote(QuoteStatus::Sent);

        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(7, $quoteId);
    }

    public function test_cross_organization_quote_not_found(): void
    {
        $quoteId = $this->quote(QuoteStatus::Accepted);

        $this->expectException(QuoteNotFoundException::class);
        $this->holder->set(2);
        $this->useCase->execute(7, $quoteId);
    }
}
