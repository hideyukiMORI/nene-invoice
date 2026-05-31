<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Invoice\CreateInvoiceInput;
use NeneInvoice\Invoice\CreateInvoiceUseCase;
use NeneInvoice\Invoice\GetInvoiceByIdUseCase;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\ListInvoicesUseCase;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GetInvoiceByIdUseCase and ListInvoicesUseCase:
 * org-scope isolation (holder-based), line-item inclusion, and pagination.
 */
final class InvoiceQueryUseCasesTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryPaymentRepository $payments;
    private InMemoryClientRepository $clients;
    private CreateInvoiceUseCase $create;
    private int $clientId;

    protected function setUp(): void
    {
        $this->holder   = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices  = new InMemoryInvoiceRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->payments  = new InMemoryPaymentRepository($this->holder);
        $this->clients   = new InMemoryClientRepository($this->holder);
        $this->clientId  = $this->clients->save(new Client(organizationId: 1, name: 'Buyer KK'));

        $this->create = new CreateInvoiceUseCase(
            $this->invoices,
            $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            new RecordingAuditRecorder(),
            $this->holder,
        );
    }

    private function createInvoice(): int
    {
        $result = $this->create->execute(null, new CreateInvoiceInput(
            clientId: $this->clientId,
            lines: [new LineItemInput('Widget', 2, 5000, 1000)],
            notes: null,
        ));

        return (int) $result->invoice->id;
    }

    // ------------------------------------------------------------------
    // GetInvoiceByIdUseCase
    // ------------------------------------------------------------------

    public function test_get_returns_invoice_with_line_items(): void
    {
        $id  = $this->createInvoice();
        $get = new GetInvoiceByIdUseCase($this->invoices, $this->lineItems, $this->payments);

        $result = $get->execute($id);

        self::assertSame($id, $result->invoice->id);
        self::assertCount(1, $result->lines);
        self::assertSame('Widget', $result->lines[0]->description);
    }

    public function test_get_throws_not_found_for_unknown_id(): void
    {
        $this->expectException(InvoiceNotFoundException::class);
        $get = new GetInvoiceByIdUseCase($this->invoices, $this->lineItems, $this->payments);
        $get->execute(999);
    }

    public function test_get_is_org_scoped(): void
    {
        $id = $this->createInvoice();

        $otherHolder = new RequestScopedHolder();
        $otherHolder->set(2);
        $otherInvoices = new InMemoryInvoiceRepository($otherHolder);

        $this->expectException(InvoiceNotFoundException::class);
        $get = new GetInvoiceByIdUseCase($otherInvoices, $this->lineItems, $this->payments);
        $get->execute($id);
    }

    // ------------------------------------------------------------------
    // ListInvoicesUseCase
    // ------------------------------------------------------------------

    public function test_list_returns_invoices_for_org(): void
    {
        $this->createInvoice();
        $this->createInvoice();

        $list   = new ListInvoicesUseCase($this->invoices, $this->payments);
        $result = $list->execute(20, 0);

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
    }

    public function test_list_is_org_scoped(): void
    {
        $this->createInvoice();

        $otherHolder = new RequestScopedHolder();
        $otherHolder->set(2);
        $otherInvoices  = new InMemoryInvoiceRepository($otherHolder);
        $otherPayments  = new InMemoryPaymentRepository($otherHolder);

        $list   = new ListInvoicesUseCase($otherInvoices, $otherPayments);
        $result = $list->execute(20, 0);

        self::assertSame(0, $result->total);
    }

    public function test_list_respects_limit_and_offset(): void
    {
        $this->createInvoice();
        $this->createInvoice();
        $this->createInvoice();

        $list = new ListInvoicesUseCase($this->invoices, $this->payments);

        $page1 = $list->execute(2, 0);
        self::assertCount(2, $page1->items);

        $page2 = $list->execute(2, 2);
        self::assertCount(1, $page2->items);
    }
}
