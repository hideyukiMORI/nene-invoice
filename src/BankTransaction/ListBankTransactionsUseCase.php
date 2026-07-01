<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Lists staged bank lines for the reconciliation workbench (#505), optionally
 * filtered by reconciliation status. Org-scoped by the repository; read-only.
 */
final readonly class ListBankTransactionsUseCase
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
    ) {
    }

    /**
     * @return array{items: list<BankTransaction>, total: int}
     */
    public function execute(?BankTransactionStatus $status, int $limit, int $offset): array
    {
        return [
            'items' => $this->transactions->findByStatus($status, $limit, $offset),
            'total' => $this->transactions->countByStatus($status),
        ];
    }
}
