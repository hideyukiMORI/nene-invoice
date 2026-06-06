<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Quote\ChangeQuoteStatusUseCase;
use NeneInvoice\Quote\InvalidStateTransitionException;
use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Quote\QuoteStatus;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryQuoteRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ChangeQuoteStatusUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryQuoteRepository $quotes;
    private RecordingAuditRecorder $audit;
    private ChangeQuoteStatusUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->quotes = new InMemoryQuoteRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->useCase = new ChangeQuoteStatusUseCase($this->quotes, new ImmediateTransactionManager(), fn () => $this->quotes, fn () => $this->audit, new FixedClock(), $this->holder);
    }

    private function draft(): int
    {
        return $this->quotes->save(new Quote(
            organizationId: 1,
            clientId: 5,
            quoteNumber: 'EST-2026-001',
            status: QuoteStatus::Draft,
            subtotalCents: 1000,
            taxCents: 100,
            totalCents: 1100,
        ));
    }

    public function test_draft_to_sent_sets_issued_at_and_audits(): void
    {
        $id = $this->draft();

        $quote = $this->useCase->execute(7, $id, QuoteStatus::Sent);

        self::assertSame(QuoteStatus::Sent, $quote->status);
        self::assertNotNull($quote->issuedAt);
        self::assertSame('quote.status_changed', $this->audit->records[0]['action']);
        self::assertSame('draft', $this->audit->records[0]['before']['status'] ?? null);
        self::assertSame('sent', $this->audit->records[0]['after']['status'] ?? null);
    }

    public function test_sent_to_accepted_is_allowed(): void
    {
        $id = $this->draft();
        $this->useCase->execute(7, $id, QuoteStatus::Sent);

        $quote = $this->useCase->execute(7, $id, QuoteStatus::Accepted);
        self::assertSame(QuoteStatus::Accepted, $quote->status);
    }

    public function test_draft_to_accepted_is_rejected(): void
    {
        $id = $this->draft();

        $this->expectException(InvalidStateTransitionException::class);
        $this->useCase->execute(7, $id, QuoteStatus::Accepted);
    }

    public function test_accepted_is_terminal(): void
    {
        $id = $this->draft();
        $this->useCase->execute(7, $id, QuoteStatus::Sent);
        $this->useCase->execute(7, $id, QuoteStatus::Accepted);

        $this->expectException(InvalidStateTransitionException::class);
        $this->useCase->execute(7, $id, QuoteStatus::Rejected);
    }

    public function test_cross_organization_quote_not_found(): void
    {
        $id = $this->draft();

        $this->expectException(QuoteNotFoundException::class);
        $this->holder->set(2);
        $this->useCase->execute(7, $id, QuoteStatus::Sent);
    }
}
