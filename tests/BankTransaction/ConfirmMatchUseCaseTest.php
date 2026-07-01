<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\BankTransaction;
use NeneInvoice\BankTransaction\BankTransactionDirection;
use NeneInvoice\BankTransaction\BankTransactionStatus;
use NeneInvoice\BankTransaction\BankTransactionValidationException;
use NeneInvoice\BankTransaction\ConfirmMatchUseCase;
use NeneInvoice\BankTransaction\PayerNameNormalizer;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\PaymentExceedsOutstandingException;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryBankTransactionRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPayerAliasRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ConfirmMatchUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryBankTransactionRepository $transactions;
    private InMemoryPayerAliasRepository $aliases;
    private InMemoryPaymentRepository $payments;
    private InMemoryInvoiceRepository $invoices;
    private RecordingAuditRecorder $audit;
    private ConfirmMatchUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->transactions = new InMemoryBankTransactionRepository($this->holder);
        $this->aliases      = new InMemoryPayerAliasRepository($this->holder);
        $this->payments     = new InMemoryPaymentRepository($this->holder);
        $this->invoices     = new InMemoryInvoiceRepository($this->holder);
        $this->audit        = new RecordingAuditRecorder();

        $recordPayment = new RecordPaymentUseCase(
            $this->payments,
            $this->invoices,
            new ImmediateTransactionManager(),
            fn () => $this->payments,
            fn () => $this->invoices,
            fn () => $this->audit,
            new FixedClock(),
            $this->holder,
        );

        $this->useCase = new ConfirmMatchUseCase($this->transactions, $this->aliases, $recordPayment);
    }

    private function issuedInvoice(int $totalCents = 11000, int $clientId = 7): int
    {
        return $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: $clientId,
            status: InvoiceStatus::Issued,
            subtotalCents: $totalCents,
            taxCents: 0,
            totalCents: $totalCents,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-29 00:00:00',
        ));
    }

    // FixedClock day is 2026-06-06 (JST); a bank value date on/before it clears the
    // "payment cannot be dated in the future" guard.
    private function credit(int $amountCents = 11000, ?string $payerName = 'カ）サンプルセイサクシヨ', ?string $bankReference = 'TXN-0001'): int
    {
        return $this->transactions->save(new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-05',
            direction: BankTransactionDirection::Credit,
            amountCents: $amountCents,
            payerName: $payerName,
            bankReference: $bankReference,
        ));
    }

    public function test_confirm_records_payment_marks_posted_and_learns_alias(): void
    {
        $invoiceId = $this->issuedInvoice(11000, 7);
        $txnId     = $this->credit(11000, 'カ）サンプルセイサクシヨ', 'TXN-1');

        $result = $this->useCase->execute(3, $txnId, $invoiceId);

        self::assertSame(BankTransactionStatus::Posted, $result->transaction->status);
        self::assertSame($invoiceId, $result->transaction->matchedInvoiceId);
        self::assertSame($result->payment->payment->id, $result->transaction->matchedPaymentId);

        self::assertSame(InvoiceStatus::Paid, $result->payment->invoice->status);
        self::assertSame(11000, $result->payment->payment->amountCents);
        self::assertSame('bank_transfer', $result->payment->payment->method);
        self::assertSame('TXN-1', $result->payment->payment->externalReference);
        self::assertSame(sprintf('bank-txn-%d', $txnId), $result->payment->payment->idempotencyKey);

        $alias = $this->aliases->findByNormalizedName(PayerNameNormalizer::normalize('カ）サンプルセイサクシヨ'));
        self::assertNotNull($alias);
        self::assertSame(7, $alias->clientId);

        self::assertNotEmpty($this->audit->records);
        self::assertSame('payment.recorded', $this->audit->records[0]['action']);

        $reloaded = $this->transactions->findById($txnId);
        self::assertNotNull($reloaded);
        self::assertSame(BankTransactionStatus::Posted, $reloaded->status);
    }

    public function test_short_deposit_records_partial_payment_without_writeoff(): void
    {
        $invoiceId = $this->issuedInvoice(11000, 7);
        $txnId     = $this->credit(10120, 'サンプル', 'TXN-2'); // short by 880 (fee tolerance)

        $result = $this->useCase->execute(3, $txnId, $invoiceId);

        self::assertSame(InvoiceStatus::PartiallyPaid, $result->payment->invoice->status);
        self::assertSame(10120, $result->payment->totalPaidCents);
        self::assertSame(BankTransactionStatus::Posted, $result->transaction->status);
    }

    public function test_overpayment_is_rejected_and_line_stays_unmatched(): void
    {
        $invoiceId = $this->issuedInvoice(5000, 7);
        $txnId     = $this->credit(11000, 'サンプル', 'TXN-3');

        try {
            $this->useCase->execute(3, $txnId, $invoiceId);
            self::fail('Expected PaymentExceedsOutstandingException.');
        } catch (PaymentExceedsOutstandingException $e) {
            self::assertSame(5000, $e->outstandingCents);
        }

        $reloaded = $this->transactions->findById($txnId);
        self::assertNotNull($reloaded);
        self::assertSame(BankTransactionStatus::Unmatched, $reloaded->status);
        self::assertSame([], $this->payments->findByInvoice($invoiceId));
    }

    public function test_debit_line_cannot_be_confirmed(): void
    {
        $invoiceId = $this->issuedInvoice(11000, 7);
        $txnId     = $this->transactions->save(new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-05',
            direction: BankTransactionDirection::Debit,
            amountCents: 11000,
            payerName: 'サンプル',
            bankReference: 'TXN-DEBIT',
        ));

        $this->expectException(BankTransactionValidationException::class);
        $this->useCase->execute(3, $txnId, $invoiceId);
    }

    public function test_retry_is_idempotent_and_does_not_double_post(): void
    {
        $invoiceId = $this->issuedInvoice(11000, 7);
        $txnId     = $this->credit(11000, 'サンプル', 'TXN-5');

        $first  = $this->useCase->execute(3, $txnId, $invoiceId);
        $second = $this->useCase->execute(3, $txnId, $invoiceId);

        self::assertSame($first->payment->payment->id, $second->payment->payment->id);
        self::assertCount(1, $this->payments->findByInvoice($invoiceId));
    }

    public function test_confirm_to_a_different_invoice_after_posting_is_rejected(): void
    {
        $invoiceA = $this->issuedInvoice(11000, 7);
        $invoiceB = $this->issuedInvoice(11000, 8);
        $txnId    = $this->credit(11000, 'サンプル', 'TXN-6');

        $this->useCase->execute(3, $txnId, $invoiceA);

        $this->expectException(BankTransactionValidationException::class);
        $this->useCase->execute(3, $txnId, $invoiceB);
    }
}
