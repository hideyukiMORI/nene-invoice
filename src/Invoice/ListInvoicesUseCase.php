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
        int $limit,
        int $offset,
        ?InvoiceListFilter $filter = null,
    ): ListInvoicesResult {
        if ($filter !== null && !$filter->isEmpty()) {
            $items = $this->invoices->findFiltered($filter, $limit, $offset);
            $total = $this->invoices->countFiltered($filter);
        } else {
            $items = $this->invoices->findAll($limit, $offset);
            $total = $this->invoices->count();
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

    /**
     * Admin list: search / filter / sort, with client names resolved by the
     * query's join (so the UI shows names, not ids).
     */
    public function executeAdmin(
        InvoiceListFilter $filter,
        InvoiceSort $sort,
        int $limit,
        int $offset,
    ): ListInvoicesResult {
        $rows  = $this->invoices->findForAdminList($filter, $sort, $limit, $offset);
        $total = $this->invoices->countForAdminList($filter);

        $items       = [];
        $ids         = [];
        $clientNames = [];
        foreach ($rows as $row) {
            $items[] = $row->invoice;
            if ($row->invoice->id !== null) {
                $ids[] = $row->invoice->id;
                $clientNames[$row->invoice->id] = $row->clientName;
            }
        }

        $paid = $this->payments->sumPaidForInvoices($ids);

        $outstanding = [];
        foreach ($items as $invoice) {
            if ($invoice->id !== null) {
                $outstanding[$invoice->id] = max(0, $invoice->totalCents - ($paid[$invoice->id] ?? 0));
            }
        }

        return new ListInvoicesResult($items, $total, $outstanding, $clientNames);
    }
}
