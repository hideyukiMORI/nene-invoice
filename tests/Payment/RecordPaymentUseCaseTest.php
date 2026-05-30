<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\PaymentExceedsOutstandingException;
use NeneInvoice\Payment\PaymentValidationException;
use NeneInvoice\Payment\RecordPaymentInput;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class RecordPaymentUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryPaymentRepository $payments;
    private InMemoryInvoiceRepository $invoices;
    private RecordingAuditRecorder $audit;
    private RecordPaymentUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->payments = new InMemoryPaymentRepository($this->holder);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->useCase = new RecordPaymentUseCase($this->payments, $this->invoices, $this->audit, $this->holder);
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

        $result = $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1000));

        self::assertSame(InvoiceStatus::PartiallyPaid, $result->invoice->status);
        self::assertSame(1000, $result->totalPaidCents);
        self::assertSame(1000, $result->payment->amountCents);
        self::assertSame('payment.recorded', $this->audit->records[0]['action']);
    }

    public function test_full_payment_moves_invoice_to_paid(): void
    {
        $id = $this->issuedInvoice(2200);

        $result = $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 2200));

        self::assertSame(InvoiceStatus::Paid, $result->invoice->status);
        self::assertSame(2200, $result->totalPaidCents);
    }

    public function test_cumulative_payments_reach_paid(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1200));
        $result = $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1000));

        self::assertSame(InvoiceStatus::Paid, $result->invoice->status);
        self::assertSame(2200, $result->totalPaidCents);
    }

    public function test_over_payment_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(PaymentExceedsOutstandingException::class);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 2201));
    }

    public function test_over_payment_exception_carries_outstanding(): void
    {
        $id = $this->issuedInvoice(2200);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 800));

        try {
            $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 2000));
            self::fail('Expected PaymentExceedsOutstandingException');
        } catch (PaymentExceedsOutstandingException $e) {
            self::assertSame(1400, $e->outstandingCents); // 2200 − 800
        }
    }

    public function test_idempotent_replay_returns_same_payment(): void
    {
        $id = $this->issuedInvoice(2200);
        $first = $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 800, idempotencyKey: 'k1'));
        $second = $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 800, idempotencyKey: 'k1'));

        self::assertSame($first->payment->id, $second->payment->id);
        // only one payment recorded despite two calls
        self::assertSame(800, $this->payments->totalPaidForInvoice($id));
    }

    public function test_non_positive_amount_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 0));
    }

    public function test_malformed_paid_at_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1000, paidAt: 'not-a-date'));
    }

    public function test_future_paid_at_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1000, paidAt: '2999-12-31'));
    }

    public function test_valid_backdated_paid_at_is_accepted(): void
    {
        $id = $this->issuedInvoice(2200);

        $result = $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1000, paidAt: '2026-05-01'));

        self::assertSame('2026-05-01', $result->payment->paidAt);
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
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1000));
    }

    public function test_payment_against_fully_paid_invoice_is_rejected(): void
    {
        $id = $this->issuedInvoice(2200);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 2200));

        $this->expectException(PaymentValidationException::class);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1));
    }

    public function test_cross_organization_invoice_not_found(): void
    {
        $id = $this->issuedInvoice(2200);

        $this->expectException(InvoiceNotFoundException::class);
        $this->holder->set(2);
        $this->useCase->execute(7, $id, new RecordPaymentInput(amountCents: 1000));
    }
}
