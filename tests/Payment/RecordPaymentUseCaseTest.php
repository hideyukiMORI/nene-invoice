<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\PaymentValidationException;
use NeneInvoice\Payment\RecordPaymentInput;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class RecordPaymentUseCaseTest extends TestCase
{
    private InMemoryPaymentRepository $payments;
    private InMemoryInvoiceRepository $invoices;
    private RecordingAuditRecorder $audit;
    private RecordPaymentUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments = new InMemoryPaymentRepository();
        $this->invoices = new InMemoryInvoiceRepository();
        $this->audit = new RecordingAuditRecorder();
        $this->useCase = new RecordPaymentUseCase($this->payments, $this->invoices, $this->audit);
    }

    private function issuedInvoice(int $totalCents = 2200): int
    {
        return $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Issued,
            subtotalCents: $totalCents,
            taxCents: 0,
            totalCents: $totalCents,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-29 00:00:00',
        ));
    }

    public function test_partial_payment_moves_invoice_to_partially_paid(): void
    {
        $id = $this->issuedInvoice(2200);

        $result = $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 1000));

        self::assertSame(InvoiceStatus::PartiallyPaid, $result->invoice->status);
        self::assertSame(1000, $result->totalPaidCents);
        self::assertSame(1000, $result->payment->amountCents);
        self::assertSame('payment.recorded', $this->audit->records[0]['action']);
    }

    public function test_full_payment_moves_invoice_to_paid(): void
    {
        $id = $this->issuedInvoice(2200);

        $result = $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 2200));

        self::assertSame(InvoiceStatus::Paid, $result->invoice->status);
        self::assertSame(2200, $result->totalPaidCents);
    }

    public function test_cumulative_payments_reach_paid(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 1200));
        $result = $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 1000));

        self::assertSame(InvoiceStatus::Paid, $result->invoice->status);
        self::assertSame(2200, $result->totalPaidCents);
    }

    public function test_over_payment_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 2201));
    }

    public function test_non_positive_amount_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 0));
    }

    public function test_payment_against_draft_is_rejected(): void
    {
        $id = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Draft,
            subtotalCents: 2200,
            taxCents: 0,
            totalCents: 2200,
        ));

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 1000));
    }

    public function test_payment_against_fully_paid_invoice_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);
        $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 2200));

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(1, 7, $id, new RecordPaymentInput(amountCents: 1));
    }

    public function test_cross_organization_invoice_not_found(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute(2, 7, $id, new RecordPaymentInput(amountCents: 1000));
    }
}
