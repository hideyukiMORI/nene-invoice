<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * A scored invoice candidate for a bank deposit (#505): the higher the `score`,
 * the more confident the match. `reasons` lists the signals that contributed
 * (e.g. `amount-exact`, `payer-alias`, `name-exact`) so the operator sees *why*
 * it was suggested. A suggestion is advice only — recording a payment is a
 * separate, operator-confirmed, compliance-reviewed step.
 */
final readonly class MatchSuggestion
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public int $invoiceId,
        public int $clientId,
        public int $score,
        public array $reasons,
    ) {
    }
}
