<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use NeneInvoice\Invoice\GetInvoiceByIdUseCase;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\ListInvoicesUseCase;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use PHPUnit\Framework\TestCase;

final class InvoiceOutstandingTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryPaymentRepository $payments;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository();
        $this->lineItems = new InMemoryLineItemRepository();
        $this->payments = new InMemoryPaymentRepository();
    }

    private function issuedInvoice(int $totalCents): int
    {
        return $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Issued,
            subtotalCents: $totalCents,
            taxCents: 0,
            totalCents: $totalCents,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-30 00:00:00',
        ));
    }

    public function test_get_computes_outstanding_as_total_minus_paid(): void
    {
        $id = $this->issuedInvoice(2200);
        $this->payments->save(new Payment(organizationId: 1, invoiceId: $id, amountCents: 800, paidAt: '2026-05-30 10:00:00'));

        $useCase = new GetInvoiceByIdUseCase($this->invoices, $this->lineItems, $this->payments);
        $result = $useCase->execute(1, $id);

        self::assertSame(1400, $result->outstandingCents);
    }

    public function test_get_outstanding_never_negative(): void
    {
        $id = $this->issuedInvoice(1000);
        $this->payments->save(new Payment(organizationId: 1, invoiceId: $id, amountCents: 1000, paidAt: '2026-05-30 10:00:00'));

        $useCase = new GetInvoiceByIdUseCase($this->invoices, $this->lineItems, $this->payments);
        self::assertSame(0, $useCase->execute(1, $id)->outstandingCents);
    }

    public function test_list_returns_outstanding_per_invoice(): void
    {
        $paidId = $this->issuedInvoice(2200);
        $this->payments->save(new Payment(organizationId: 1, invoiceId: $paidId, amountCents: 2200, paidAt: '2026-05-30 10:00:00'));
        $unpaidId = $this->issuedInvoice(5000);

        $useCase = new ListInvoicesUseCase($this->invoices, $this->payments);
        $result = $useCase->execute(1, 20, 0);

        self::assertSame(0, $result->outstandingByInvoiceId[$paidId] ?? null);
        self::assertSame(5000, $result->outstandingByInvoiceId[$unpaidId] ?? null);
    }
}
