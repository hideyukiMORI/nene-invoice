<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\PaymentNotFoundException;
use NeneInvoice\Payment\RecordPaymentInput;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\Payment\VoidPaymentUseCase;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class VoidPaymentUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryPaymentRepository $payments;
    private RecordingAuditRecorder $audit;
    private RecordPaymentUseCase $record;
    private VoidPaymentUseCase $void;
    private int $invoiceId;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->payments = new InMemoryPaymentRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->record = new RecordPaymentUseCase($this->payments, $this->invoices, new ImmediateTransactionManager(), fn () => $this->payments, fn () => $this->invoices, $this->audit, new FixedClock(), $this->holder);
        $this->void = new VoidPaymentUseCase($this->payments, $this->invoices, new ImmediateTransactionManager(), fn () => $this->payments, fn () => $this->invoices, $this->audit, $this->holder);

        $this->invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Issued,
            subtotalCents: 2200,
            taxCents: 0,
            totalCents: 2200,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-04-01 00:00:00',
        ));
    }

    private function recordPayment(int $amount): int
    {
        $result = $this->record->execute(null, $this->invoiceId, new RecordPaymentInput(amountCents: $amount, paidAt: '2026-04-20'));

        return (int) $result->payment->id;
    }

    public function test_void_full_payment_returns_invoice_to_issued(): void
    {
        $paymentId = $this->recordPayment(2200);
        self::assertSame(InvoiceStatus::Paid, $this->invoices->findById($this->invoiceId)?->status);

        $result = $this->void->execute(null, $this->invoiceId, $paymentId, 'operator reversal');

        self::assertSame(InvoiceStatus::Issued, $result->invoice->status);
        self::assertSame(0, $result->totalPaidCents);
        $lastAudit = end($this->audit->records);
        self::assertIsArray($lastAudit);
        self::assertSame('payment.voided', $lastAudit['action']);
    }

    public function test_void_one_of_two_payments_keeps_partially_paid(): void
    {
        $first = $this->recordPayment(800);
        $this->recordPayment(600); // total 1400 → partially_paid

        $result = $this->void->execute(null, $this->invoiceId, $first, null);

        self::assertSame(InvoiceStatus::PartiallyPaid, $result->invoice->status);
        self::assertSame(600, $result->totalPaidCents);
    }

    public function test_void_is_idempotent(): void
    {
        $paymentId = $this->recordPayment(800);
        $this->void->execute(null, $this->invoiceId, $paymentId, null);
        $again = $this->void->execute(null, $this->invoiceId, $paymentId, null);

        self::assertSame(InvoiceStatus::Issued, $again->invoice->status);
        self::assertSame(0, $again->totalPaidCents);
    }

    public function test_unknown_payment_is_not_found(): void
    {
        $this->expectException(PaymentNotFoundException::class);
        $this->void->execute(null, $this->invoiceId, 999, null);
    }

    public function test_cross_organization_payment_is_not_found(): void
    {
        $paymentId = $this->recordPayment(800);

        $this->expectException(PaymentNotFoundException::class);
        $this->holder->set(2);
        $this->void->execute(null, $this->invoiceId, $paymentId, null);
    }
}
