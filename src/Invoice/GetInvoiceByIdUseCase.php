<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class GetInvoiceByIdUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
    ) {
    }

    /** @throws InvoiceNotFoundException */
    public function execute(int $organizationId, int $id): InvoiceWithLines
    {
        $invoice = $this->invoices->findById($id);

        if ($invoice === null || $invoice->organizationId !== $organizationId) {
            throw new InvoiceNotFoundException($id);
        }

        return new InvoiceWithLines($invoice, $this->lineItems->findByParent(LineItemParent::Invoice, $id));
    }
}
