<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Scores unpaid invoices against a bank deposit to suggest reconciliations (#505).
 *
 * Pure and deterministic: it combines three signals — amount fit, a learned
 * {@see PayerAlias} hit, and remitter↔client name similarity — into an
 * explainable score, and returns the candidates ranked best-first. It only
 * *suggests*; recording a payment is a separate, operator-confirmed,
 * compliance-reviewed step (accounting-compliance.md), so the fee tolerance here
 * affects ranking only, never a write-off.
 *
 * Only `credit` (入金) transactions are matched against receivables.
 */
final class BankTransactionMatcher
{
    public const SCORE_AMOUNT_EXACT      = 50;
    public const SCORE_AMOUNT_WITHIN_FEE = 35;
    public const SCORE_AMOUNT_PARTIAL    = 15;
    public const SCORE_AMOUNT_OVER       = 5;
    public const SCORE_ALIAS             = 40;
    public const SCORE_NAME_EXACT        = 30;
    public const SCORE_NAME_CONTAINS     = 18;
    public const SCORE_NAME_SIMILAR_MAX  = 15;

    /** Largest common bank-transfer fee (JPY): a deposit short by up to this is likely fee-netted. */
    public const FEE_TOLERANCE_CENTS = 880;

    /** Minimum similar_text ratio (0..1) for a name to contribute. */
    public const NAME_SIMILAR_THRESHOLD = 0.6;

    /**
     * @param list<MatchCandidate> $candidates
     * @param ?int                 $aliasClientId client the remitter is already mapped to, if any
     * @return list<MatchSuggestion> best-first
     */
    public static function suggest(BankTransaction $transaction, array $candidates, ?int $aliasClientId = null): array
    {
        if ($transaction->direction !== BankTransactionDirection::Credit) {
            return [];
        }

        $payer = $transaction->payerName !== null ? PayerNameNormalizer::normalize($transaction->payerName) : '';

        $suggestions = [];
        foreach ($candidates as $candidate) {
            [$score, $reasons] = self::scoreCandidate($transaction, $candidate, $aliasClientId, $payer);

            if ($score > 0) {
                $suggestions[] = new MatchSuggestion($candidate->invoiceId, $candidate->clientId, $score, $reasons);
            }
        }

        // Best score first; ties broken by invoice id ascending (deterministic).
        usort($suggestions, static fn (MatchSuggestion $a, MatchSuggestion $b): int => ($b->score <=> $a->score) ?: ($a->invoiceId <=> $b->invoiceId));

        return $suggestions;
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private static function scoreCandidate(BankTransaction $transaction, MatchCandidate $candidate, ?int $aliasClientId, string $payer): array
    {
        $score   = 0;
        $reasons = [];

        $amount      = $transaction->amountCents;
        $outstanding = $candidate->outstandingCents;

        if ($amount === $outstanding) {
            $score    += self::SCORE_AMOUNT_EXACT;
            $reasons[] = 'amount-exact';
        } elseif ($amount < $outstanding && ($outstanding - $amount) <= self::FEE_TOLERANCE_CENTS) {
            $score    += self::SCORE_AMOUNT_WITHIN_FEE;
            $reasons[] = 'amount-within-fee';
        } elseif ($amount < $outstanding) {
            $score    += self::SCORE_AMOUNT_PARTIAL;
            $reasons[] = 'amount-partial';
        } else {
            $score    += self::SCORE_AMOUNT_OVER;
            $reasons[] = 'amount-over';
        }

        if ($aliasClientId !== null && $candidate->clientId === $aliasClientId) {
            $score    += self::SCORE_ALIAS;
            $reasons[] = 'payer-alias';

            return [$score, $reasons];
        }

        $client = $candidate->clientName !== null ? PayerNameNormalizer::normalize($candidate->clientName) : '';
        if ($payer !== '' && $client !== '') {
            if ($payer === $client) {
                $score    += self::SCORE_NAME_EXACT;
                $reasons[] = 'name-exact';
            } elseif (str_contains($client, $payer) || str_contains($payer, $client)) {
                $score    += self::SCORE_NAME_CONTAINS;
                $reasons[] = 'name-contains';
            } else {
                $ratio = self::similarity($payer, $client);
                if ($ratio >= self::NAME_SIMILAR_THRESHOLD) {
                    $score    += (int) round($ratio * self::SCORE_NAME_SIMILAR_MAX);
                    $reasons[] = 'name-similar';
                }
            }
        }

        return [$score, $reasons];
    }

    private static function similarity(string $a, string $b): float
    {
        $percent = 0.0;
        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
