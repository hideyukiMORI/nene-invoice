<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
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
final readonly class VoidPaymentUseCase implements VoidPaymentUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): PaymentRepositoryInterface $paymentsFactory
     * @param Closure(DatabaseQueryExecutorInterface): InvoiceRepositoryInterface $invoicesFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private InvoiceRepositoryInterface $invoices,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $paymentsFactory,
        private Closure $invoicesFactory,
        private Closure $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @throws InvoiceNotFoundException
     * @throws PaymentNotFoundException
     */
    public function execute(
        ?int $actorUserId,
        int $invoiceId,
        int $paymentId,
        ?string $reason,
    ): RecordPaymentResult {
        $payment = $this->payments->findById($paymentId);

        if (
            $payment === null
            || $payment->invoiceId !== $invoiceId
        ) {
            throw new PaymentNotFoundException($paymentId);
        }

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        // Idempotent: already voided → return current state without re-voiding.
        if ($payment->isDeleted) {
            return new RecordPaymentResult($payment, $invoice, $this->payments->totalPaidForInvoice($invoiceId));
        }

        $before          = InvoiceResponse::toArray($invoice);
        $organizationId  = $this->orgId->get();

        // The void, the recomputed invoice status, and the audit record commit
        // atomically — the two writes can no longer diverge (Issue #352).
        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $actorUserId,
            $organizationId,
            $invoiceId,
            $paymentId,
            $invoice,
            $payment,
            $reason,
            $before,
        ): RecordPaymentResult {
            $payments = ($this->paymentsFactory)($exec);

            $payments->markVoided($paymentId);

            $totalPaid = $payments->totalPaidForInvoice($invoiceId);
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

            ($this->invoicesFactory)($exec)->update($updatedInvoice);

            $after = InvoiceResponse::toArray($updatedInvoice);
            $after['voided_payment_id'] = $paymentId;
            $after['void_reason'] = $reason;

            ($this->auditFactory)($exec)->record($actorUserId, $organizationId, 'payment.voided', 'invoice', $invoiceId, $before, $after);

            $voided = $payments->findById($paymentId) ?? $payment;

            return new RecordPaymentResult($voided, $updatedInvoice, $totalPaid);
        });
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
