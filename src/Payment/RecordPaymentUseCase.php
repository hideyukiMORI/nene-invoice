<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Closure;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Support\Jst;

/**
 * Records a payment against an issued invoice and advances the invoice status:
 * partial payment → partially_paid, full payment → paid.
 *
 * Compliance: amounts are integer cents (ADR 0004). Only issued invoices accept
 * payments — a draft has no liability yet and a paid invoice has none left.
 * Over-payment is rejected: the recorded total may not exceed the invoice total
 * (refunds / over-receipts are a separate operation, deliberately not handled here).
 */
final readonly class RecordPaymentUseCase implements RecordPaymentUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): PaymentRepositoryInterface $paymentsFactory
     * @param Closure(DatabaseQueryExecutorInterface): InvoiceRepositoryInterface $invoicesFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private InvoiceRepositoryInterface $invoices,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $paymentsFactory,
        private Closure $invoicesFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @throws InvoiceNotFoundException
     * @throws PaymentValidationException
     * @throws PaymentExceedsOutstandingException
     */
    public function execute(?int $actorUserId, int $invoiceId, RecordPaymentInput $input): RecordPaymentResult
    {
        $organizationId = $this->orgId->get();

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        // Idempotent replay: a retried write with the same key returns the existing
        // payment instead of recording a duplicate (ADR 0009 §3.1.2).
        if ($input->idempotencyKey !== null) {
            $existing = $this->payments->findByIdempotencyKey($input->idempotencyKey);
            if ($existing !== null) {
                return new RecordPaymentResult($existing, $invoice, $this->payments->totalPaidForInvoice($invoiceId));
            }
        }

        if ($input->amountCents <= 0) {
            throw new PaymentValidationException('A payment amount must be a positive number of cents.');
        }

        // Validate an explicit payment date: a malformed value must surface as a
        // 422 (not a 500 from the DATETIME column), and a payment cannot be dated
        // in the future (accounting integrity, diagnostic R2-4 / R2-5).
        if ($input->paidAt !== null) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $input->paidAt)
                ?: \DateTimeImmutable::createFromFormat('!Y-m-d', $input->paidAt);

            if ($parsed === false) {
                throw new PaymentValidationException('A payment date must be a valid date (YYYY-MM-DD).');
            }

            // The supplied value is a JST calendar date; reject anything after the
            // current JST day (compare calendar dates, zone-independent — ADR 0010).
            if ($parsed->format('Y-m-d') > Jst::of($this->clock->now())->format('Y-m-d')) {
                throw new PaymentValidationException('A payment date cannot be in the future.');
            }
        }

        if (!in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
            throw new PaymentValidationException('Payments can only be recorded against an issued invoice.');
        }

        $alreadyPaid = $this->payments->totalPaidForInvoice($invoiceId);

        if ($alreadyPaid + $input->amountCents > $invoice->totalCents) {
            throw new PaymentExceedsOutstandingException(max(0, $invoice->totalCents - $alreadyPaid));
        }

        $before = InvoiceResponse::toArray($invoice);

        $paidAt    = $input->paidAt ?? $this->clock->now()->format('Y-m-d H:i:s');
        $totalPaid = $alreadyPaid + $input->amountCents;
        $newStatus = $totalPaid >= $invoice->totalCents ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid;

        $updatedInvoice = new Invoice(
            organizationId: $invoice->organizationId,
            clientId: $invoice->clientId,
            status: $newStatus,
            subtotalCents: $invoice->subtotalCents,
            taxCents: $invoice->taxCents,
            totalCents: $invoice->totalCents,
            isQualifiedInvoice: $invoice->isQualifiedInvoice,
            quoteId: $invoice->quoteId,
            invoiceNumber: $invoice->invoiceNumber,
            issuedAt: $invoice->issuedAt,
            dueAt: $invoice->dueAt,
            notes: $invoice->notes,
            id: $invoice->id,
            createdAt: $invoice->createdAt,
            updatedAt: $invoice->updatedAt,
        );

        // The payment insert, the invoice status update, and the audit record all
        // commit atomically — a partial write (e.g. payment saved but status stale)
        // can no longer occur (Issue #352).
        $payment = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $actorUserId,
            $organizationId,
            $invoiceId,
            $input,
            $paidAt,
            $updatedInvoice,
            $before,
        ): Payment {
            $payments = ($this->paymentsFactory)($exec);

            $paymentId = $payments->save(new Payment(
                organizationId: $organizationId,
                invoiceId: $invoiceId,
                amountCents: $input->amountCents,
                paidAt: $paidAt,
                method: $input->method,
                note: $input->note,
                externalReference: $input->externalReference,
                idempotencyKey: $input->idempotencyKey,
            ));

            ($this->invoicesFactory)($exec)->update($updatedInvoice);

            $payment = null;
            foreach ($payments->findByInvoice($invoiceId) as $candidate) {
                if ($candidate->id === $paymentId) {
                    $payment = $candidate;

                    break;
                }
            }

            if ($payment === null) {
                throw new LogicException('Payment disappeared immediately after recording.');
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'payment.recorded',
                entityType: 'invoice',
                entityId: $invoiceId,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: $before,
                after: InvoiceResponse::toArray($updatedInvoice),
            ));

            return $payment;
        });

        return new RecordPaymentResult($payment, $updatedInvoice, $totalPaid);
    }
}
