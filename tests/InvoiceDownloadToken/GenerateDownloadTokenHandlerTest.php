<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\InvoiceDownloadToken;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Routing\Router;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\InvoiceDownloadToken\GenerateDownloadTokenHandler;
use NeneInvoice\InvoiceDownloadToken\GenerateDownloadTokenUseCase;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryInvoiceDownloadTokenRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class GenerateDownloadTokenHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryInvoiceRepository $invoices;
    private GenerateDownloadTokenHandler $handler;
    private int $invoiceId;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->psr17    = new Psr17Factory();
        $this->holder   = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
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
            new GenerateDownloadTokenUseCase($this->invoices, new ImmediateTransactionManager(), fn () => $tokens, new RecordingAuditRecorder(), new FixedClock(), $this->holder),
            new JsonResponseFactory($this->psr17, $this->psr17),
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

    public function test_throws_invoice_not_found_for_cross_org_invoice(): void
    {
        // The holder resolves a different organization than the invoice's, so the
        // org-scoped invoice repository hides it (InvoiceNotFoundException, which
        // propagates to InvoiceNotFoundExceptionHandler in production).
        $this->holder->set(2);

        $this->expectException(\NeneInvoice\Invoice\InvoiceNotFoundException::class);

        $request = $this->psr17->createServerRequest('POST', "/admin/invoices/{$this->invoiceId}/download-token")
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId]);

        $this->handler->handle($request);
    }

    // Note: the "no org context" case is now handled upstream by
    // OrgResolverMiddleware (404), not by this handler (ADR 0006).
}
