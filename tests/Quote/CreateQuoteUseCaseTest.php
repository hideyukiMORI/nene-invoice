<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Quote\CreateQuoteInput;
use NeneInvoice\Quote\CreateQuoteUseCase;
use NeneInvoice\Quote\QuoteStatus;
use NeneInvoice\Quote\QuoteValidationException;
use NeneInvoice\Support\Jst;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\InMemoryDocumentSequenceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryQuoteRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreateQuoteUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryQuoteRepository $quotes;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private InMemoryCompanySettingsRepository $companySettings;
    private RecordingAuditRecorder $audit;
    private FixedClock $clock;
    private CreateQuoteUseCase $useCase;
    private int $clientId;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->quotes = new InMemoryQuoteRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->clients = new InMemoryClientRepository($this->holder);
        $this->companySettings = new InMemoryCompanySettingsRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->clock = new FixedClock();
        $this->clientId = $this->clients->save(new Client(organizationId: 1, name: 'Acme'));

        $this->useCase = new CreateQuoteUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->quotes,
            fn () => $this->lineItems,
            $this->clients,
            $this->companySettings,
            new DocumentNumberGenerator(new InMemoryDocumentSequenceRepository()),
            new TaxCalculator(),
            fn () => $this->audit,
            $this->clock,
            $this->holder,
        );
    }

    public function test_applies_company_default_validity_when_not_provided(): void
    {
        $this->companySettings->save(new CompanySettings(
            organizationId: 1,
            legalName: 'X',
            defaultQuoteValidityDays: 30,
        ));

        $result = $this->useCase->execute(1, new CreateQuoteInput(
            clientId: $this->clientId,
            lines: [new LineItemInput('X', 1, 1000, 1000)],
        ));

        // Mirror the use case: the validity period is measured from the JST
        // calendar date of the (fixed) clock, never the real wall clock — keep the
        // expectation deterministic regardless of when the suite runs.
        $expected = Jst::of($this->clock->now())->setTime(0, 0)->modify('+30 day')->format('Y-m-d');
        self::assertSame($expected, substr((string) $result->quote->validUntil, 0, 10));
    }

    public function test_explicit_valid_until_overrides_company_default(): void
    {
        $this->companySettings->save(new CompanySettings(
            organizationId: 1,
            legalName: 'X',
            defaultQuoteValidityDays: 30,
        ));

        $result = $this->useCase->execute(1, new CreateQuoteInput(
            clientId: $this->clientId,
            lines: [new LineItemInput('X', 1, 1000, 1000)],
            validUntil: '2026-12-31',
        ));

        self::assertSame('2026-12-31', substr((string) $result->quote->validUntil, 0, 10));
    }

    public function test_creates_quote_with_computed_totals_number_lines_and_audit(): void
    {
        $result = $this->useCase->execute(42, new CreateQuoteInput(
            clientId: $this->clientId,
            lines: [
                new LineItemInput('Standard', 1, 1000, 1000), // ¥1000 @10% → 100
                new LineItemInput('Reduced', 1, 1000, 800),   // ¥1000 @8%  → 80
            ],
        ));

        self::assertSame(QuoteStatus::Draft, $result->quote->status);
        self::assertSame(2000, $result->quote->subtotalCents);
        self::assertSame(180, $result->quote->taxCents);
        self::assertSame(2180, $result->quote->totalCents);
        self::assertStringStartsWith('EST-', $result->quote->quoteNumber);
        self::assertStringEndsWith('-001', $result->quote->quoteNumber);

        self::assertCount(2, $result->lines);
        self::assertSame(LineItemParent::Quote, $result->lines[0]->parentType);

        self::assertCount(1, $this->audit->records);
        self::assertSame('quote.created', $this->audit->records[0]['action']);
        self::assertSame(42, $this->audit->records[0]['actor_user_id']);
    }

    public function test_rejects_client_from_another_organization(): void
    {
        $otherClient = $this->clients->save(new Client(organizationId: 2, name: 'Other'));

        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(1, new CreateQuoteInput($otherClient, [new LineItemInput('X', 1, 1000, 1000)]));
    }

    public function test_rejects_empty_lines(): void
    {
        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(1, new CreateQuoteInput($this->clientId, []));
    }

    public function test_rejects_disallowed_tax_rate(): void
    {
        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 1, 1000, 500)]));
    }

    /** Only 800/1000 bps are allowed; every adjacent value is rejected (§3). */
    #[DataProvider('disallowedTaxRates')]
    public function test_rejects_tax_rate_outside_allowed_set(int $rateBps): void
    {
        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 1, 1000, $rateBps)]));
    }

    /** @return iterable<string, array{int}> */
    public static function disallowedTaxRates(): iterable
    {
        yield '0 bps'              => [0];
        yield '799 (below 8%)'     => [799];
        yield '801 (above 8%)'     => [801];
        yield '999 (below 10%)'    => [999];
        yield '1001 (above 10%)'   => [1001];
        yield '2000 (20%)'         => [2000];
        yield 'negative'           => [-800];
    }

    #[DataProvider('allowedTaxRates')]
    public function test_accepts_allowed_tax_rate(int $rateBps): void
    {
        $result = $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 1, 1000, $rateBps)]));

        self::assertSame(QuoteStatus::Draft, $result->quote->status);
    }

    /** @return iterable<string, array{int}> */
    public static function allowedTaxRates(): iterable
    {
        yield '8% (lower boundary)'  => [800];
        yield '10% (upper boundary)' => [1000];
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 0, 1000, 1000)]));
    }

    public function test_rejects_negative_quantity(): void
    {
        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', -1, 1000, 1000)]));
    }

    public function test_accepts_minimum_quantity_of_one(): void
    {
        $result = $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 1, 1000, 1000)]));

        self::assertSame(1000, $result->quote->subtotalCents);
    }

    public function test_accepts_large_quantity_without_overflow(): void
    {
        $result = $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 1_000_000, 1000, 1000)]));

        self::assertSame(1_000_000_000, $result->quote->subtotalCents);
        self::assertSame(100_000_000, $result->quote->taxCents);
    }

    public function test_rejects_negative_unit_price(): void
    {
        $this->expectException(QuoteValidationException::class);
        $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 1, -1, 1000)]));
    }

    public function test_accepts_zero_unit_price(): void
    {
        // Zero is the lower boundary of the >= 0 rule (free/discount lines).
        $result = $this->useCase->execute(1, new CreateQuoteInput($this->clientId, [new LineItemInput('X', 1, 0, 1000)]));

        self::assertSame(0, $result->quote->subtotalCents);
        self::assertSame(0, $result->quote->taxCents);
    }
}
