<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

interface BankTransactionRepositoryInterface
{
    public function save(BankTransaction $transaction): int;

    public function findById(int $id): ?BankTransaction;

    /** @return list<BankTransaction> */
    public function findByOrganization(int $limit, int $offset): array;

    public function countByOrganization(): int;

    /**
     * Org-scoped page of staged lines, optionally filtered by reconciliation
     * status (null = every status), newest first (value_date desc, id desc).
     *
     * @return list<BankTransaction>
     */
    public function findByStatus(?BankTransactionStatus $status, int $limit, int $offset): array;

    /** Org-scoped count of staged lines, optionally filtered by status (null = all). */
    public function countByStatus(?BankTransactionStatus $status): int;

    /**
     * The org-scoped row already imported with this bank line identifier, or
     * null. Used to skip re-importing the same line (idempotent import).
     */
    public function findByBankReference(string $bankReference): ?BankTransaction;

    /** @throws BankTransactionNotFoundException */
    public function update(BankTransaction $transaction): void;
}
