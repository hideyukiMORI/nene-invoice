<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Invoice\InvoiceStatus;

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
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private InvoiceRepositoryInterface $invoices,
        private AuditRecorderInterface $audit,
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

            if ($parsed > new \DateTimeImmutable('now')) {
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

        $paidAt = $input->paidAt ?? date('Y-m-d H:i:s');

        $paymentId = $this->payments->save(new Payment(
            organizationId: $organizationId,
            invoiceId: $invoiceId,
            amountCents: $input->amountCents,
            paidAt: $paidAt,
            method: $input->method,
            note: $input->note,
            externalReference: $input->externalReference,
            idempotencyKey: $input->idempotencyKey,
        ));

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

        $this->invoices->update($updatedInvoice);

        $stored = $this->payments->findByInvoice($invoiceId);
        $payment = null;

        foreach ($stored as $candidate) {
            if ($candidate->id === $paymentId) {
                $payment = $candidate;

                break;
            }
        }

        if ($payment === null) {
            throw new LogicException('Payment disappeared immediately after recording.');
        }

        $this->audit->record(
            $actorUserId,
            $organizationId,
            'payment.recorded',
            'invoice',
            $invoiceId,
            $before,
            InvoiceResponse::toArray($updatedInvoice),
        );

        return new RecordPaymentResult($payment, $updatedInvoice, $totalPaid);
    }
}
