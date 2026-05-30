<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use NeneInvoice\Client\Client;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Quote\CreateQuoteInput;
use NeneInvoice\Quote\CreateQuoteUseCase;
use NeneInvoice\Quote\GetQuoteByIdUseCase;
use NeneInvoice\Quote\ListQuotesUseCase;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryDocumentSequenceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryQuoteRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GetQuoteByIdUseCase and ListQuotesUseCase:
 * org-scope isolation, line-item inclusion, and pagination.
 */
final class QuoteQueryUseCasesTest extends TestCase
{
    private InMemoryQuoteRepository $quotes;
    private InMemoryLineItemRepository $lineItems;
    private ?int $quoteId;

    protected function setUp(): void
    {
        $this->quotes    = new InMemoryQuoteRepository();
        $this->lineItems = new InMemoryLineItemRepository();
        $clients         = new InMemoryClientRepository();
        $clientId        = $clients->save(new Client(organizationId: 1, name: 'Acme'));

        $createUseCase = new CreateQuoteUseCase(
            $this->quotes,
            $this->lineItems,
            $clients,
            new DocumentNumberGenerator(new InMemoryDocumentSequenceRepository()),
            new TaxCalculator(),
            new RecordingAuditRecorder(),
        );

        $this->quoteId = $createUseCase->execute(1, null, new CreateQuoteInput(
            clientId: $clientId,
            lines: [new LineItemInput('コンサル', 1, 50000, 1000)],
            validUntil: null,
            notes: null,
        ))->quote->id;
    }

    // ----------------------------- GetQuoteById -----------------------------

    public function test_get_returns_quote_with_line_items(): void
    {
        self::assertNotNull($this->quoteId);
        $useCase = new GetQuoteByIdUseCase($this->quotes, $this->lineItems);
        $result  = $useCase->execute(1, $this->quoteId);

        self::assertSame($this->quoteId, $result->quote->id);
        self::assertCount(1, $result->lines);
        self::assertSame('コンサル', $result->lines[0]->description);
    }

    public function test_get_hides_quote_from_another_organization(): void
    {
        self::assertNotNull($this->quoteId);
        $useCase = new GetQuoteByIdUseCase($this->quotes, $this->lineItems);

        $this->expectException(QuoteNotFoundException::class);
        $useCase->execute(2, $this->quoteId);
    }

    public function test_get_throws_for_nonexistent_id(): void
    {
        $useCase = new GetQuoteByIdUseCase($this->quotes, $this->lineItems);

        $this->expectException(QuoteNotFoundException::class);
        $useCase->execute(1, 9999);
    }

    // ------------------------------ ListQuotes ------------------------------

    public function test_list_is_scoped_to_organization(): void
    {
        $clients2  = new InMemoryClientRepository();
        $client2Id = $clients2->save(new Client(organizationId: 2, name: 'OtherOrg'));
        $quotes2   = new InMemoryQuoteRepository();

        (new CreateQuoteUseCase(
            $quotes2,
            new InMemoryLineItemRepository(),
            $clients2,
            new DocumentNumberGenerator(new InMemoryDocumentSequenceRepository()),
            new TaxCalculator(),
            new RecordingAuditRecorder(),
        ))->execute(2, null, new CreateQuoteInput(
            clientId: $client2Id,
            lines: [new LineItemInput('Other', 1, 10000, 1000)],
            validUntil: null,
            notes: null,
        ));

        $useCase = new ListQuotesUseCase($this->quotes);

        // org 1 sees 1 quote, org 2 sees its own separately
        self::assertSame(1, $useCase->execute(1, 20, 0)->total);
        self::assertSame(0, $useCase->execute(2, 20, 0)->total);
    }

    public function test_list_pagination_limit_and_offset(): void
    {
        $clients = new InMemoryClientRepository();
        $cid     = $clients->save(new Client(organizationId: 1, name: 'Acme'));
        $create  = new CreateQuoteUseCase(
            $this->quotes,
            $this->lineItems,
            $clients,
            new DocumentNumberGenerator(new InMemoryDocumentSequenceRepository()),
            new TaxCalculator(),
            new RecordingAuditRecorder(),
        );

        // setUp already has 1 quote; add 4 more
        for ($i = 0; $i < 4; $i++) {
            $create->execute(1, null, new CreateQuoteInput(
                clientId: $cid,
                lines: [new LineItemInput("Item {$i}", 1, 1000, 1000)],
                validUntil: null,
                notes: null,
            ));
        }

        $useCase = new ListQuotesUseCase($this->quotes);
        $page    = $useCase->execute(1, 3, 0);
        self::assertSame(5, $page->total);
        self::assertCount(3, $page->items);

        $page2 = $useCase->execute(1, 3, 3);
        self::assertCount(2, $page2->items);
    }
}
