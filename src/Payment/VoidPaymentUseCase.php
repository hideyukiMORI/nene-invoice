<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Invoice\InvoiceStatus;

/**
 * Voids a payment on a reconciliation reversal (ADR 0009 §3.2). This is a
 * **void-with-audit**, never a hard delete — financial history is preserved
 * (accounting-compliance.md). The invoice status is recomputed from the
 * remaining valid payments. Idempotent: voiding an already-voided payment is a
 * no-op success.
 */
final readonly class VoidPaymentUseCase
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private InvoiceRepositoryInterface $invoices,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * @throws InvoiceNotFoundException
     * @throws PaymentNotFoundException
     */
    public function execute(
        int $organizationId,
        ?int $actorUserId,
        int $invoiceId,
        int $paymentId,
        ?string $reason,
    ): RecordPaymentResult {
        $payment = $this->payments->findById($paymentId);

        if (
            $payment === null
            || $payment->organizationId !== $organizationId
            || $payment->invoiceId !== $invoiceId
        ) {
            throw new PaymentNotFoundException($paymentId);
        }

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null || $invoice->organizationId !== $organizationId) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        // Idempotent: already voided → return current state without re-voiding.
        if ($payment->isDeleted) {
            return new RecordPaymentResult($payment, $invoice, $this->payments->totalPaidForInvoice($invoiceId));
        }

        $before = InvoiceResponse::toArray($invoice);

        $this->payments->markVoided($paymentId);

        $totalPaid = $this->payments->totalPaidForInvoice($invoiceId);
        $newStatus = $this->recomputeStatus($invoice, $totalPaid);

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

        $after = InvoiceResponse::toArray($updatedInvoice);
        $after['voided_payment_id'] = $paymentId;
        $after['void_reason'] = $reason;

        $this->audit->record($actorUserId, $organizationId, 'payment.voided', 'invoice', $invoiceId, $before, $after);

        $voided = $this->payments->findById($paymentId) ?? $payment;

        return new RecordPaymentResult($voided, $updatedInvoice, $totalPaid);
    }

    /**
     * After a void, recompute from the remaining valid payments. An issued invoice
     * with no remaining payments returns to `issued`; partial → `partially_paid`;
     * fully covered → `paid`. (Drafts cannot have payments.)
     */
    private function recomputeStatus(Invoice $invoice, int $totalPaid): InvoiceStatus
    {
        if ($totalPaid <= 0) {
            return InvoiceStatus::Issued;
        }

        return $totalPaid >= $invoice->totalCents ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid;
    }
}
