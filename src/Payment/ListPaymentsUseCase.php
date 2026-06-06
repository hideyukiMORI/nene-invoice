<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;

/**
 * Lists the payments recorded against an invoice, scoped to the caller's
 * organization (cross-org access surfaces as not-found, never a leak).
 */
final readonly class ListPaymentsUseCase implements ListPaymentsUseCaseInterface
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private InvoiceRepositoryInterface $invoices,
    ) {
    }

    /**
     * @return list<Payment>
     *
     * @throws InvoiceNotFoundException
     */
    public function execute(int $invoiceId): array
    {
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        return $this->payments->findByInvoice($invoiceId);
    }
}
