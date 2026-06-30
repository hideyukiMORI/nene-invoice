<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * An unpaid invoice a bank deposit might settle (#505), handed to
 * {@see BankTransactionMatcher} for scoring. `outstandingCents` is the
 * still-owed balance; `clientName` is the buyer's name (ideally `name_kana`),
 * normalized by the matcher for comparison against the remitter.
 */
final readonly class MatchCandidate
{
    public function __construct(
        public int $invoiceId,
        public int $clientId,
        public int $outstandingCents,
        public ?string $clientName = null,
    ) {
    }
}
