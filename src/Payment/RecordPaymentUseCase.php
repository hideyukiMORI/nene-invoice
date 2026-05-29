<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use LogicException;
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
final readonly class RecordPaymentUseCase
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private InvoiceRepositoryInterface $invoices,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * @throws InvoiceNotFoundException
     * @throws PaymentValidationException
     */
    public function execute(int $organizationId, ?int $actorUserId, int $invoiceId, RecordPaymentInput $input): RecordPaymentResult
    {
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null || $invoice->organizationId !== $organizationId) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        if ($input->amountCents <= 0) {
            throw new PaymentValidationException('A payment amount must be a positive number of cents.');
        }

        if (!in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
            throw new PaymentValidationException('Payments can only be recorded against an issued invoice.');
        }

        $alreadyPaid = $this->payments->totalPaidForInvoice($invoiceId);

        if ($alreadyPaid + $input->amountCents > $invoice->totalCents) {
            throw new PaymentValidationException('The payment would exceed the invoice total (over-payment is not allowed).');
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
