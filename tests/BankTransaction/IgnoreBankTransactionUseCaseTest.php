<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\BankTransaction;
use NeneInvoice\BankTransaction\BankTransactionDirection;
use NeneInvoice\BankTransaction\BankTransactionNotFoundException;
use NeneInvoice\BankTransaction\BankTransactionStatus;
use NeneInvoice\BankTransaction\BankTransactionValidationException;
use NeneInvoice\BankTransaction\IgnoreBankTransactionUseCase;
use NeneInvoice\Tests\Support\InMemoryBankTransactionRepository;
use PHPUnit\Framework\TestCase;

final class IgnoreBankTransactionUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryBankTransactionRepository $transactions;
    private IgnoreBankTransactionUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->transactions = new InMemoryBankTransactionRepository($this->holder);
        $this->useCase      = new IgnoreBankTransactionUseCase($this->transactions);
    }

    private function stage(BankTransactionStatus $status = BankTransactionStatus::Unmatched): int
    {
        return $this->transactions->save(new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-05',
            direction: BankTransactionDirection::Credit,
            amountCents: 11000,
            payerName: 'サンプル',
            bankReference: 'TXN-IGN',
            status: $status,
        ));
    }

    public function test_ignore_marks_line_ignored(): void
    {
        $id = $this->stage();

        $result = $this->useCase->execute($id);

        self::assertSame(BankTransactionStatus::Ignored, $result->status);
        $reloaded = $this->transactions->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame(BankTransactionStatus::Ignored, $reloaded->status);
    }

    public function test_ignoring_an_ignored_line_is_a_no_op(): void
    {
        $id = $this->stage(BankTransactionStatus::Ignored);

        $result = $this->useCase->execute($id);

        self::assertSame(BankTransactionStatus::Ignored, $result->status);
    }

    public function test_posted_line_cannot_be_ignored(): void
    {
        $id = $this->stage(BankTransactionStatus::Posted);

        $this->expectException(BankTransactionValidationException::class);
        $this->useCase->execute($id);
    }

    public function test_missing_transaction_throws(): void
    {
        $this->expectException(BankTransactionNotFoundException::class);
        $this->useCase->execute(999);
    }
}
