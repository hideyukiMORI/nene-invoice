<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use NeneInvoice\BankTransaction\BankTransaction;
use NeneInvoice\BankTransaction\BankTransactionDirection;
use NeneInvoice\BankTransaction\BankTransactionMatcher;
use NeneInvoice\BankTransaction\MatchCandidate;
use PHPUnit\Framework\TestCase;

final class BankTransactionMatcherTest extends TestCase
{
    private function credit(int $amountCents, ?string $payer = null): BankTransaction
    {
        return new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-30',
            direction: BankTransactionDirection::Credit,
            amountCents: $amountCents,
            payerName: $payer,
        );
    }

    public function test_exact_amount_and_alias_scores_highest(): void
    {
        $candidates = [
            new MatchCandidate(invoiceId: 1, clientId: 5, outstandingCents: 11000, clientName: 'ネネショウカイ'),
            new MatchCandidate(invoiceId: 2, clientId: 9, outstandingCents: 5000, clientName: 'アアショウジ'),
        ];

        $suggestions = BankTransactionMatcher::suggest($this->credit(11000, 'ｶ)ﾈﾈ'), $candidates, aliasClientId: 5);

        self::assertSame(1, $suggestions[0]->invoiceId);
        self::assertSame(
            BankTransactionMatcher::SCORE_AMOUNT_EXACT + BankTransactionMatcher::SCORE_ALIAS,
            $suggestions[0]->score,
        );
        self::assertContains('amount-exact', $suggestions[0]->reasons);
        self::assertContains('payer-alias', $suggestions[0]->reasons);
    }

    public function test_deposit_short_by_a_transfer_fee_is_recognized(): void
    {
        $candidates  = [new MatchCandidate(invoiceId: 1, clientId: 5, outstandingCents: 11000)];
        $suggestions = BankTransactionMatcher::suggest($this->credit(10780), $candidates);

        self::assertContains('amount-within-fee', $suggestions[0]->reasons);
        self::assertSame(BankTransactionMatcher::SCORE_AMOUNT_WITHIN_FEE, $suggestions[0]->score);
    }

    public function test_name_match_without_alias(): void
    {
        $candidates = [new MatchCandidate(invoiceId: 1, clientId: 5, outstandingCents: 11000, clientName: 'ネネシヨウカイ（カ')];

        $suggestions = BankTransactionMatcher::suggest($this->credit(11000, 'ｶ)ﾈﾈｼﾖｳｶｲ'), $candidates);

        self::assertContains('name-exact', $suggestions[0]->reasons);
        self::assertSame(
            BankTransactionMatcher::SCORE_AMOUNT_EXACT + BankTransactionMatcher::SCORE_NAME_EXACT,
            $suggestions[0]->score,
        );
    }

    public function test_results_are_ranked_best_first(): void
    {
        $candidates = [
            new MatchCandidate(invoiceId: 1, clientId: 1, outstandingCents: 3000),        // amount-over (deposit > owed)
            new MatchCandidate(invoiceId: 2, clientId: 2, outstandingCents: 11000),       // amount-exact
            new MatchCandidate(invoiceId: 3, clientId: 3, outstandingCents: 30000),       // amount-partial
        ];

        $suggestions = BankTransactionMatcher::suggest($this->credit(11000), $candidates);

        self::assertSame([2, 3, 1], array_map(static fn ($s): int => $s->invoiceId, $suggestions));
    }

    public function test_partial_payment_is_scored_low_not_zero(): void
    {
        $candidates  = [new MatchCandidate(invoiceId: 1, clientId: 5, outstandingCents: 30000)];
        $suggestions = BankTransactionMatcher::suggest($this->credit(8000), $candidates);

        self::assertContains('amount-partial', $suggestions[0]->reasons);
        self::assertSame(BankTransactionMatcher::SCORE_AMOUNT_PARTIAL, $suggestions[0]->score);
    }

    public function test_debit_transactions_are_not_matched(): void
    {
        $debit = new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-30',
            direction: BankTransactionDirection::Debit,
            amountCents: 11000,
        );

        self::assertSame([], BankTransactionMatcher::suggest($debit, [new MatchCandidate(1, 5, 11000)]));
    }
}
