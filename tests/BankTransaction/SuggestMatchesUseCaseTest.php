<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\BankTransaction;
use NeneInvoice\BankTransaction\BankTransactionDirection;
use NeneInvoice\BankTransaction\BankTransactionNotFoundException;
use NeneInvoice\BankTransaction\OpenReceivable;
use NeneInvoice\BankTransaction\PayerAlias;
use NeneInvoice\BankTransaction\PayerNameNormalizer;
use NeneInvoice\BankTransaction\SuggestMatchesUseCase;
use NeneInvoice\Tests\Support\InMemoryBankTransactionRepository;
use NeneInvoice\Tests\Support\InMemoryMatchCandidateRepository;
use NeneInvoice\Tests\Support\InMemoryPayerAliasRepository;
use PHPUnit\Framework\TestCase;

final class SuggestMatchesUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryBankTransactionRepository $transactions;
    private InMemoryPayerAliasRepository $aliases;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->transactions = new InMemoryBankTransactionRepository($this->holder);
        $this->aliases      = new InMemoryPayerAliasRepository($this->holder);
    }

    /**
     * @param list<OpenReceivable> $receivables
     */
    private function useCase(array $receivables): SuggestMatchesUseCase
    {
        return new SuggestMatchesUseCase(
            $this->transactions,
            new InMemoryMatchCandidateRepository($receivables),
            $this->aliases,
        );
    }

    private function credit(int $amountCents = 11000, ?string $payerName = 'カ）サンプルセイサクシヨ'): int
    {
        return $this->transactions->save(new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-30',
            direction: BankTransactionDirection::Credit,
            amountCents: $amountCents,
            payerName: $payerName,
            bankReference: 'TXN-0001',
        ));
    }

    public function test_ranks_exact_amount_and_name_match_first_with_display_fields(): void
    {
        $id = $this->credit(11000);

        $suggestions = $this->useCase([
            new OpenReceivable(invoiceId: 20, clientId: 8, outstandingCents: 5000, invoiceNumber: 'INV-2026-020', clientName: 'べつ商店', clientNameKana: 'ベツシヨウテン'),
            new OpenReceivable(invoiceId: 10, clientId: 7, outstandingCents: 11000, invoiceNumber: 'INV-2026-010', clientName: 'サンプル製作所', clientNameKana: 'サンプルセイサクシヨ'),
        ])->execute($id);

        self::assertNotEmpty($suggestions);
        self::assertSame(10, $suggestions[0]->invoiceId);
        self::assertSame('INV-2026-010', $suggestions[0]->invoiceNumber);
        self::assertSame('サンプル製作所', $suggestions[0]->clientName);
        self::assertSame(11000, $suggestions[0]->outstandingCents);
        self::assertContains('amount-exact', $suggestions[0]->reasons);
        self::assertContains('name-exact', $suggestions[0]->reasons);
        // The exact match outranks the unrelated smaller receivable.
        self::assertGreaterThan($suggestions[1]->score, $suggestions[0]->score);
    }

    public function test_learned_alias_pulls_its_client_to_the_top(): void
    {
        $id      = $this->credit(9000, 'フリガナフメイ');
        $payerId = $this->aliases->upsert(new PayerAlias(
            organizationId: 1,
            normalizedName: PayerNameNormalizer::normalize('フリガナフメイ'),
            clientId: 42,
        ));
        self::assertGreaterThan(0, $payerId);

        $suggestions = $this->useCase([
            new OpenReceivable(invoiceId: 30, clientId: 9, outstandingCents: 9000, invoiceNumber: 'INV-2026-030', clientName: '別会社', clientNameKana: 'ベツガイシヤ'),
            new OpenReceivable(invoiceId: 31, clientId: 42, outstandingCents: 9000, invoiceNumber: 'INV-2026-031', clientName: 'エイリアス先', clientNameKana: 'エイリアスサキ'),
        ])->execute($id);

        self::assertSame(31, $suggestions[0]->invoiceId);
        self::assertContains('payer-alias', $suggestions[0]->reasons);
    }

    public function test_debit_line_yields_no_suggestions(): void
    {
        $id = $this->transactions->save(new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-30',
            direction: BankTransactionDirection::Debit,
            amountCents: 11000,
            payerName: 'テスト',
            bankReference: 'TXN-DEBIT',
        ));

        $suggestions = $this->useCase([
            new OpenReceivable(invoiceId: 10, clientId: 7, outstandingCents: 11000, invoiceNumber: 'INV-2026-010'),
        ])->execute($id);

        self::assertSame([], $suggestions);
    }

    public function test_missing_transaction_throws(): void
    {
        $this->expectException(BankTransactionNotFoundException::class);

        $this->useCase([])->execute(999);
    }
}
