<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * A scored invoice suggestion for a bank deposit (#505), enriched for display:
 * the {@see BankTransactionMatcher} score and `reasons` plus the invoice number,
 * client name, and outstanding balance the operator needs to decide. Advice only —
 * recording a payment is a separate, operator-confirmed step.
 */
final readonly class SuggestedMatch
{
    /**
     * @param list<string> $reasons signals that contributed (e.g. amount-exact, payer-alias)
     */
    public function __construct(
        public int $invoiceId,
        public int $clientId,
        public int $outstandingCents,
        public int $score,
        public array $reasons,
        public ?string $invoiceNumber = null,
        public ?string $clientName = null,
    ) {
    }
}
