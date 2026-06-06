<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;

final readonly class GetInvoiceByIdUseCase implements GetInvoiceByIdUseCaseInterface
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private PaymentRepositoryInterface $payments,
    ) {
    }

    /** @throws InvoiceNotFoundException */
    public function execute(int $id): InvoiceWithLines
    {
        $invoice = $this->invoices->findById($id);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($id);
        }

        $lines = $this->lineItems->findByParent(LineItemParent::Invoice, $id);
        $outstanding = max(0, $invoice->totalCents - $this->payments->totalPaidForInvoice($id));

        return new InvoiceWithLines($invoice, $lines, $outstanding);
    }
}
