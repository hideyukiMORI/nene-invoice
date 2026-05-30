<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\ListPaymentsUseCase;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use PHPUnit\Framework\TestCase;

final class ListPaymentsUseCaseTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;
    private InMemoryPaymentRepository $payments;
    private ListPaymentsUseCase $useCase;
    private int $invoiceId;

    protected function setUp(): void
    {
        $this->invoices  = new InMemoryInvoiceRepository();
        $this->payments  = new InMemoryPaymentRepository();
        $this->useCase   = new ListPaymentsUseCase($this->payments, $this->invoices);
        $this->invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 10000,
            taxCents: 1000,
            totalCents: 11000,
        ));
    }

    public function test_returns_payments_for_invoice(): void
    {
        $this->payments->save(new Payment(organizationId: 1, invoiceId: $this->invoiceId, amountCents: 5000, paidAt: '2026-05-01 00:00:00'));
        $this->payments->save(new Payment(organizationId: 1, invoiceId: $this->invoiceId, amountCents: 6000, paidAt: '2026-05-10 00:00:00'));

        $list = $this->useCase->execute(1, $this->invoiceId);

        self::assertCount(2, $list);
        self::assertSame(5000, $list[0]->amountCents);
        self::assertSame(6000, $list[1]->amountCents);
    }

    public function test_returns_empty_when_no_payments(): void
    {
        $list = $this->useCase->execute(1, $this->invoiceId);
        self::assertCount(0, $list);
    }

    public function test_throws_for_cross_org_invoice(): void
    {
        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute(2, $this->invoiceId);
    }

    public function test_throws_for_nonexistent_invoice(): void
    {
        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute(1, 9999);
    }

    public function test_excludes_payments_of_other_invoices(): void
    {
        $otherId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 1000,
            taxCents: 0,
            totalCents: 1000,
        ));
        $this->payments->save(new Payment(organizationId: 1, invoiceId: $otherId, amountCents: 999, paidAt: '2026-05-01 00:00:00'));

        $list = $this->useCase->execute(1, $this->invoiceId);
        self::assertCount(0, $list);
    }
}
