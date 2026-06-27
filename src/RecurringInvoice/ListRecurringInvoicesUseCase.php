<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

final readonly class ListRecurringInvoicesUseCase
{
    public function __construct(
        private RecurringInvoiceRepositoryInterface $recurring,
    ) {
    }

    public function execute(int $limit, int $offset): ListRecurringInvoicesResult
    {
        return new ListRecurringInvoicesResult(
            $this->recurring->findByOrganization($limit, $offset),
            $this->recurring->countByOrganization(),
        );
    }
}
