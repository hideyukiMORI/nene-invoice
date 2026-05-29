<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

final readonly class ListInvoicesUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
    ) {
    }

    public function execute(int $organizationId, int $limit, int $offset): ListInvoicesResult
    {
        return new ListInvoicesResult(
            $this->invoices->findAllByOrganization($organizationId, $limit, $offset),
            $this->invoices->countByOrganization($organizationId),
        );
    }
}
