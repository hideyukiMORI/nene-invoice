<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\InvoiceDownloadToken;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Invoice\GenerateInvoicePdfUseCase;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\Pdf\InvoicePdfGenerator;
use NeneInvoice\InvoiceDownloadToken\DownloadInvoicePdfHandler;
use NeneInvoice\InvoiceDownloadToken\InvoiceDownloadToken;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceDownloadTokenRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class DownloadInvoicePdfHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryInvoiceDownloadTokenRepository $tokens;
    private DownloadInvoicePdfHandler $handler;
    private int $invoiceId;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();

        // The handler sets this holder from the validated token record's org.
        $holder      = new \Nene2\Http\RequestScopedHolder();
        $invoices    = new InMemoryInvoiceRepository($holder);
        $lineItems   = new InMemoryLineItemRepository();
        $payments    = new InMemoryPaymentRepository();
        $clients     = new InMemoryClientRepository($holder);
        $company     = new InMemoryCompanySettingsRepository();
        $this->tokens = new InMemoryInvoiceDownloadTokenRepository();

        $clientId = $clients->save(new Client(organizationId: 1, name: '株式会社テスト'));
        $company->save(new CompanySettings(organizationId: 1, legalName: '発行者テスト'));
        $this->invoiceId = $invoices->save(new Invoice(
            organizationId: 1,
            clientId: $clientId,
            status: InvoiceStatus::Issued,
            subtotalCents: 10000,
            taxCents: 1000,
            totalCents: 11000,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-01 00:00:00',
        ));

        $this->handler = new DownloadInvoicePdfHandler(
            $this->tokens,
            new GenerateInvoicePdfUseCase($invoices, $lineItems, $payments, $company, $clients, $holder),
            new InvoicePdfGenerator(new TaxCalculator()),
            $this->psr17,
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
            $holder,
        );
    }

    public function test_streams_pdf_for_valid_token(): void
    {
        $rawToken  = 'valid-test-token-abc123';
        $tokenHash = hash('sha256', $rawToken);
        $this->tokens->save(new InvoiceDownloadToken(
            invoiceId: $this->invoiceId,
            organizationId: 1,
            tokenHash: $tokenHash,
            expiresAt: '2099-12-31 23:59:59',
            createdAt: '2026-05-31 00:00:00',
        ));

        $request = $this->psr17->createServerRequest('GET', "/invoices/download/{$rawToken}")
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['token' => $rawToken]);

        $response = $this->handler->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('inline;', $response->getHeaderLine('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $response->getBody());
    }

    public function test_returns_404_for_expired_token(): void
    {
        $rawToken  = 'expired-token-xyz';
        $tokenHash = hash('sha256', $rawToken);
        $this->tokens->save(new InvoiceDownloadToken(
            invoiceId: $this->invoiceId,
            organizationId: 1,
            tokenHash: $tokenHash,
            expiresAt: '2020-01-01 00:00:00',
            createdAt: '2019-12-25 00:00:00',
        ));

        $request = $this->psr17->createServerRequest('GET', "/invoices/download/{$rawToken}")
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['token' => $rawToken]);

        self::assertSame(404, $this->handler->handle($request)->getStatusCode());
    }

    public function test_returns_404_for_unknown_token(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/invoices/download/no-such-token')
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['token' => 'no-such-token']);

        self::assertSame(404, $this->handler->handle($request)->getStatusCode());
    }

    public function test_pdf_filename_contains_invoice_number(): void
    {
        $rawToken  = 'filename-test-token';
        $this->tokens->save(new InvoiceDownloadToken(
            invoiceId: $this->invoiceId,
            organizationId: 1,
            tokenHash: hash('sha256', $rawToken),
            expiresAt: '2099-12-31 23:59:59',
            createdAt: '2026-05-31 00:00:00',
        ));

        $request = $this->psr17->createServerRequest('GET', "/invoices/download/{$rawToken}")
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['token' => $rawToken]);

        $response = $this->handler->handle($request);
        self::assertStringContainsString('INV-2026-001', $response->getHeaderLine('Content-Disposition'));
    }
}
