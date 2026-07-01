<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Suggests invoices a staged bank deposit might settle (#505).
 *
 * Loads the open receivables, resolves any learned {@see PayerAlias} for the
 * remitter, and runs the pure {@see BankTransactionMatcher} to score and rank
 * them, then enriches each suggestion with the invoice number, client name, and
 * outstanding balance for display. Read-only: it records no payment (matching has
 * zero accounting impact — accounting-compliance.md); only `credit` lines yield
 * suggestions.
 */
final readonly class SuggestMatchesUseCase
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private MatchCandidateRepositoryInterface $candidates,
        private PayerAliasRepositoryInterface $aliases,
    ) {
    }

    /**
     * @return list<SuggestedMatch> best-first
     *
     * @throws BankTransactionNotFoundException
     */
    public function execute(int $bankTransactionId): array
    {
        $transaction = $this->transactions->findById($bankTransactionId);

        if ($transaction === null) {
            throw new BankTransactionNotFoundException($bankTransactionId);
        }

        $aliasClientId = $this->resolveAlias($transaction->payerName);

        $receivables = $this->candidates->findOpenReceivables();

        $matchCandidates = array_map(
            static fn (OpenReceivable $r): MatchCandidate => new MatchCandidate(
                invoiceId: $r->invoiceId,
                clientId: $r->clientId,
                outstandingCents: $r->outstandingCents,
                clientName: $r->matchName(),
            ),
            $receivables,
        );

        /** @var array<int, OpenReceivable> $byInvoiceId */
        $byInvoiceId = [];
        foreach ($receivables as $receivable) {
            $byInvoiceId[$receivable->invoiceId] = $receivable;
        }

        $suggestions = BankTransactionMatcher::suggest($transaction, $matchCandidates, $aliasClientId);

        // Every suggestion's invoice id came from a candidate we built out of
        // $byInvoiceId, so the lookup always resolves.
        return array_map(static function (MatchSuggestion $s) use ($byInvoiceId): SuggestedMatch {
            $receivable = $byInvoiceId[$s->invoiceId];

            return new SuggestedMatch(
                invoiceId: $s->invoiceId,
                clientId: $s->clientId,
                outstandingCents: $receivable->outstandingCents,
                score: $s->score,
                reasons: $s->reasons,
                invoiceNumber: $receivable->invoiceNumber,
                clientName: $receivable->clientName,
            );
        }, $suggestions);
    }

    private function resolveAlias(?string $payerName): ?int
    {
        if ($payerName === null) {
            return null;
        }

        $normalized = PayerNameNormalizer::normalize($payerName);
        if ($normalized === '') {
            return null;
        }

        return $this->aliases->findByNormalizedName($normalized)?->clientId;
    }
}
