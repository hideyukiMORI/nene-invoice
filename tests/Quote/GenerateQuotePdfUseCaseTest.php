<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Quote\CreateQuoteInput;
use NeneInvoice\Quote\CreateQuoteUseCase;
use NeneInvoice\Quote\GenerateQuotePdfUseCase;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\InMemoryDocumentSequenceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryQuoteRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Tests the data-assembly phase of QuotePdfUseCase. PDF rendering (mPDF) is
 * excluded as it requires the gd extension which is not available in CI.
 */
final class GenerateQuotePdfUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryQuoteRepository $quotes;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private InMemoryCompanySettingsRepository $companySettings;
    private GenerateQuotePdfUseCase $useCase;
    private int $clientId;

    protected function setUp(): void
    {
        $this->holder          = new RequestScopedHolder();
        $this->holder->set(1);
        $this->quotes          = new InMemoryQuoteRepository($this->holder);
        $this->lineItems       = new InMemoryLineItemRepository();
        $this->clients         = new InMemoryClientRepository($this->holder);
        $this->companySettings = new InMemoryCompanySettingsRepository();

        $this->useCase = new GenerateQuotePdfUseCase(
            $this->quotes,
            $this->lineItems,
            $this->companySettings,
            $this->clients,
            $this->holder,
        );

        $this->clientId = $this->clients->save(new Client(
            organizationId: 1,
            name: 'Buyer KK',
        ));
    }

    private function createQuote(): int
    {
        $create = new CreateQuoteUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->quotes,
            fn () => $this->lineItems,
            $this->clients,
            $this->companySettings,
            new DocumentNumberGenerator(new InMemoryDocumentSequenceRepository()),
            new TaxCalculator(),
            fn () => new RecordingAuditRecorder(),
            new FixedClock(),
            $this->holder,
        );

        $result = $create->execute(null, new CreateQuoteInput(
            clientId: $this->clientId,
            lines: [new LineItemInput('コンサル', 1, 50000, 1000)],
            validUntil: null,
            notes: null,
        ));

        return (int) $result->quote->id;
    }

    public function test_assembles_quote_with_lines_company_and_client(): void
    {
        $this->companySettings->save(new CompanySettings(
            organizationId: 1,
            legalName: 'テスト商会',
            registrationNumber: 'T1234567890123',
        ));

        $quoteId = $this->createQuote();
        $data    = $this->useCase->execute($quoteId);

        self::assertSame($quoteId, $data->quoteWithLines->quote->id);
        self::assertCount(1, $data->quoteWithLines->lines);
        self::assertSame('テスト商会', $data->companySettings->legalName);
        self::assertSame('Buyer KK', $data->client->name);
    }

    public function test_throws_not_found_for_unknown_quote(): void
    {
        $this->expectException(QuoteNotFoundException::class);
        $this->useCase->execute(999);
    }

    public function test_uses_fallback_company_when_settings_missing(): void
    {
        $quoteId = $this->createQuote();
        $data    = $this->useCase->execute($quoteId);

        self::assertStringContainsString('未設定', $data->companySettings->legalName);
    }

    public function test_is_org_scoped(): void
    {
        $quoteId = $this->createQuote();

        $otherHolder = new RequestScopedHolder();
        $otherHolder->set(2);
        $otherQuotes  = new InMemoryQuoteRepository($otherHolder);
        $otherUseCase = new GenerateQuotePdfUseCase(
            $otherQuotes,
            $this->lineItems,
            $this->companySettings,
            $this->clients,
            $otherHolder,
        );

        $this->expectException(QuoteNotFoundException::class);
        $otherUseCase->execute($quoteId);
    }
}
