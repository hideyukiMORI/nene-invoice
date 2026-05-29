<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\Payment\PaymentRepositoryInterface;

final readonly class ListInvoicesUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
    ) {
    }

    public function execute(
        int $organizationId,
        int $limit,
        int $offset,
        ?InvoiceListFilter $filter = null,
    ): ListInvoicesResult {
        if ($filter !== null && !$filter->isEmpty()) {
            $items = $this->invoices->findByOrganizationFiltered($organizationId, $filter, $limit, $offset);
            $total = $this->invoices->countByOrganizationFiltered($organizationId, $filter);
        } else {
            $items = $this->invoices->findAllByOrganization($organizationId, $limit, $offset);
            $total = $this->invoices->countByOrganization($organizationId);
        }

        $ids = [];
        foreach ($items as $invoice) {
            if ($invoice->id !== null) {
                $ids[] = $invoice->id;
            }
        }

        $paid = $this->payments->sumPaidForInvoices($ids);

        $outstanding = [];
        foreach ($items as $invoice) {
            if ($invoice->id !== null) {
                $outstanding[$invoice->id] = max(0, $invoice->totalCents - ($paid[$invoice->id] ?? 0));
            }
        }

        return new ListInvoicesResult($items, $total, $outstanding);
    }
}
