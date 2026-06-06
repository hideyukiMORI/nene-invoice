<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Quote\CreateQuoteInput;
use NeneInvoice\Quote\CreateQuoteUseCase;
use NeneInvoice\Quote\GetQuoteByIdUseCase;
use NeneInvoice\Quote\ListQuotesUseCase;
use NeneInvoice\Quote\QuoteListFilter;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Quote\QuoteSort;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\InMemoryDocumentSequenceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryQuoteRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GetQuoteByIdUseCase and ListQuotesUseCase:
 * org-scope isolation (holder-based), line-item inclusion, and pagination.
 */
final class QuoteQueryUseCasesTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryQuoteRepository $quotes;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private CreateQuoteUseCase $create;
    private int $clientId;
    private ?int $quoteId;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->quotes    = new InMemoryQuoteRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->clients   = new InMemoryClientRepository($this->holder);
        $this->clientId  = $this->clients->save(new Client(organizationId: 1, name: 'Acme'));

        $this->create = new CreateQuoteUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->quotes,
            fn () => $this->lineItems,
            $this->clients,
            new InMemoryCompanySettingsRepository($this->holder),
            new DocumentNumberGenerator(new InMemoryDocumentSequenceRepository()),
            new TaxCalculator(),
            new RecordingAuditRecorder(),
            $this->holder,
        );

        $this->quoteId = $this->create->execute(null, new CreateQuoteInput(
            clientId: $this->clientId,
            lines: [new LineItemInput('コンサル', 1, 50000, 1000)],
            validUntil: null,
            notes: null,
        ))->quote->id;
    }

    // ----------------------------- GetQuoteById -----------------------------

    public function test_get_returns_quote_with_line_items(): void
    {
        self::assertNotNull($this->quoteId);
        $result = (new GetQuoteByIdUseCase($this->quotes, $this->lineItems))->execute($this->quoteId);

        self::assertSame($this->quoteId, $result->quote->id);
        self::assertCount(1, $result->lines);
        self::assertSame('コンサル', $result->lines[0]->description);
    }

    public function test_get_hides_quote_from_another_organization(): void
    {
        self::assertNotNull($this->quoteId);
        $this->holder->set(2);

        $this->expectException(QuoteNotFoundException::class);
        (new GetQuoteByIdUseCase($this->quotes, $this->lineItems))->execute($this->quoteId);
    }

    public function test_get_throws_for_nonexistent_id(): void
    {
        $this->expectException(QuoteNotFoundException::class);
        (new GetQuoteByIdUseCase($this->quotes, $this->lineItems))->execute(9999);
    }

    // ------------------------------ ListQuotes ------------------------------

    public function test_list_is_scoped_to_organization(): void
    {
        $useCase = new ListQuotesUseCase($this->quotes);

        // org 1 has the quote from setUp; org 2 sees none (holder-scoped)
        self::assertSame(1, $useCase->executeAdmin(new QuoteListFilter(), new QuoteSort(), 20, 0)->total);

        $this->holder->set(2);
        self::assertSame(0, $useCase->executeAdmin(new QuoteListFilter(), new QuoteSort(), 20, 0)->total);
    }

    public function test_list_pagination_limit_and_offset(): void
    {
        // setUp already has 1 quote; add 4 more
        for ($i = 0; $i < 4; $i++) {
            $this->create->execute(null, new CreateQuoteInput(
                clientId: $this->clientId,
                lines: [new LineItemInput("Item {$i}", 1, 1000, 1000)],
                validUntil: null,
                notes: null,
            ));
        }

        $useCase = new ListQuotesUseCase($this->quotes);
        $page    = $useCase->executeAdmin(new QuoteListFilter(), new QuoteSort(), 3, 0);
        self::assertSame(5, $page->total);
        self::assertCount(3, $page->items);

        $page2 = $useCase->executeAdmin(new QuoteListFilter(), new QuoteSort(), 3, 3);
        self::assertCount(2, $page2->items);
    }
}
