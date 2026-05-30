<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\InvoiceDownloadToken;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\InvoiceDownloadToken\GenerateDownloadTokenHandler;
use NeneInvoice\InvoiceDownloadToken\GenerateDownloadTokenUseCase;
use NeneInvoice\Tests\Support\InMemoryInvoiceDownloadTokenRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class GenerateDownloadTokenHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryInvoiceRepository $invoices;
    private GenerateDownloadTokenHandler $handler;
    private int $invoiceId;

    protected function setUp(): void
    {
        $this->psr17    = new Psr17Factory();
        $this->invoices = new InMemoryInvoiceRepository();
        $tokens         = new InMemoryInvoiceDownloadTokenRepository();

        $this->invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 1000,
            taxCents: 0,
            totalCents: 1000,
        ));

        $this->handler = new GenerateDownloadTokenHandler(
            new GenerateDownloadTokenUseCase($this->invoices, $tokens),
            new JsonResponseFactory($this->psr17, $this->psr17),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    public function test_generates_token_and_returns_url(): void
    {
        $request = $this->psr17->createServerRequest('POST', "/admin/invoices/{$this->invoiceId}/download-token")
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'admin', 'org' => 1])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId]);

        $response = $this->handler->handle($request);
        self::assertSame(201, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringStartsWith('/invoices/download/', (string) $body['url']);
        self::assertNotEmpty($body['expires_at']);
    }

    public function test_returns_400_without_org_context(): void
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/invoices/1/download-token')
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => '1']);

        self::assertSame(400, $this->handler->handle($request)->getStatusCode());
    }

    public function test_throws_invoice_not_found_for_cross_org_invoice(): void
    {
        // InvoiceNotFoundException propagates to InvoiceNotFoundExceptionHandler in production.
        // Unit tests exercise the handler in isolation, so we assert the exception directly.
        $this->expectException(\NeneInvoice\Invoice\InvoiceNotFoundException::class);

        $request = $this->psr17->createServerRequest('POST', "/admin/invoices/{$this->invoiceId}/download-token")
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'admin', 'org' => 2])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId]);

        $this->handler->handle($request);
    }
}
