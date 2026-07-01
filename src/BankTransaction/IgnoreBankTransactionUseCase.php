<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Dismisses a staged bank line the operator will not reconcile (#505) — bank fees,
 * non-AR transfers, duplicates — moving it to `ignored` so it drops out of the
 * matching queue. Staging-only: it touches no billing record. A line whose payment
 * is already `posted` cannot be ignored; re-ignoring an ignored line is a no-op.
 */
final readonly class IgnoreBankTransactionUseCase
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
    ) {
    }

    /**
     * @throws BankTransactionNotFoundException
     * @throws BankTransactionValidationException
     */
    public function execute(int $bankTransactionId): BankTransaction
    {
        $transaction = $this->transactions->findById($bankTransactionId);

        if ($transaction === null) {
            throw new BankTransactionNotFoundException($bankTransactionId);
        }

        if ($transaction->status === BankTransactionStatus::Posted) {
            throw new BankTransactionValidationException('A reconciled bank line cannot be ignored.');
        }

        if ($transaction->status === BankTransactionStatus::Ignored) {
            return $transaction;
        }

        $ignored = new BankTransaction(
            organizationId: $transaction->organizationId,
            valueDate: $transaction->valueDate,
            direction: $transaction->direction,
            amountCents: $transaction->amountCents,
            payerName: $transaction->payerName,
            description: $transaction->description,
            bankReference: $transaction->bankReference,
            status: BankTransactionStatus::Ignored,
            matchedInvoiceId: $transaction->matchedInvoiceId,
            matchedPaymentId: $transaction->matchedPaymentId,
            importedAt: $transaction->importedAt,
            id: $transaction->id,
            createdAt: $transaction->createdAt,
            updatedAt: $transaction->updatedAt,
        );
        $this->transactions->update($ignored);

        return $ignored;
    }
}
