<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * One line imported from a bank deposit CSV (#505): the staging unit for
 * auto-reconciliation (自動消込). Amounts are integer cents (ADR 0004) and
 * `valueDate` is a JST calendar date (`Y-m-d`).
 *
 * A bank transaction is raw imported data, not a billing record — it carries no
 * accounting weight until an operator confirms a match and a payment is recorded
 * (a later, compliance-reviewed step). `bankReference` is the bank's own line
 * identifier, used to skip re-importing the same line and, on posting, to seed
 * the payment's `external_reference`.
 */
final readonly class BankTransaction
{
    public function __construct(
        public int $organizationId,
        public string $valueDate,
        public BankTransactionDirection $direction,
        public int $amountCents,
        public ?string $payerName = null,
        public ?string $description = null,
        public ?string $bankReference = null,
        public BankTransactionStatus $status = BankTransactionStatus::Unmatched,
        public ?int $matchedInvoiceId = null,
        public ?int $matchedPaymentId = null,
        public ?string $importedAt = null,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
