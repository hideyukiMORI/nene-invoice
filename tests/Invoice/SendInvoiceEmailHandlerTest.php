<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Routing\Router;
use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\Pdf\InvoicePdfData;
use NeneInvoice\Invoice\Pdf\InvoicePdfGeneratorInterface;
use NeneInvoice\Invoice\SendInvoiceEmailHandler;
use NeneInvoice\Invoice\SendInvoiceEmailUseCase;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use NeneInvoice\Tests\Support\RecordingMailer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * Covers #626: demo orgs get a 200 preview (no send); real orgs get 204 (send).
 */
final class SendInvoiceEmailHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private RecordingMailer $mailer;
    private RecordingAuditRecorder $audit;
    private int $invoiceId;
    private SendInvoiceEmailUseCase $useCase;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();

        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $holder->set(1);

        $invoices        = new InMemoryInvoiceRepository($holder);
        $clients         = new InMemoryClientRepository($holder);
        $companySettings = new InMemoryCompanySettingsRepository();
        $lineItems       = new InMemoryLineItemRepository();
        $this->mailer    = new RecordingMailer();
        $this->audit     = new RecordingAuditRecorder();

        $companySettings->save(new CompanySettings(organizationId: 1, legalName: 'テスト商会'));
        $clientId = $clients->save(new Client(
            organizationId: 1,
            name: '株式会社サンプル',
            email: 'buyer@example.com',
        ));
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

        $pdfGenerator = new class () implements InvoicePdfGeneratorInterface {
            public function generate(InvoicePdfData $data): string
            {
                return '%PDF-1.4 fake';
            }
        };

        $this->useCase = new SendInvoiceEmailUseCase(
            $invoices,
            $lineItems,
            $clients,
            $companySettings,
            $pdfGenerator,
            $this->mailer,
            $this->audit,
            $holder,
            'NeNe Invoice',
        );
    }

    private function handler(string $demoSlugPrefix): SendInvoiceEmailHandler
    {
        return new SendInvoiceEmailHandler(
            $this->useCase,
            new JsonResponseFactory($this->psr17, $this->psr17),
            $demoSlugPrefix,
        );
    }

    public function test_demo_org_returns_preview_json_and_does_not_send(): void
    {
        $request = $this->psr17->createServerRequest('POST', "/admin/invoices/{$this->invoiceId}/send-email")
            ->withAttribute('nene2.org.slug', 'demo-abc123')
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId]);

        $response = $this->handler('demo-')->handle($request);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($body['preview']);
        self::assertSame('buyer@example.com', $body['recipient']);
        self::assertStringContainsString('INV-2026-001', (string) $body['subject']);
        self::assertStringContainsString('株式会社サンプル', (string) $body['body_html']);

        // No real send, no audit event.
        self::assertCount(0, $this->mailer->sent);
        self::assertCount(0, $this->audit->records);
    }

    public function test_non_demo_org_sends_and_returns_204(): void
    {
        $request = $this->psr17->createServerRequest('POST', "/admin/invoices/{$this->invoiceId}/send-email")
            ->withAttribute('nene2.org.slug', 'acme')
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'role' => 'admin', 'org' => 1])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId]);

        $response = $this->handler('demo-')->handle($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertCount(1, $this->mailer->sent);
        self::assertSame('buyer@example.com', $this->mailer->sent[0]->toAddress);
        self::assertCount(1, $this->audit->records);
    }

    public function test_empty_prefix_disables_demo_detection_and_sends(): void
    {
        $request = $this->psr17->createServerRequest('POST', "/admin/invoices/{$this->invoiceId}/send-email")
            ->withAttribute('nene2.org.slug', 'demo-abc123')
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'role' => 'admin', 'org' => 1])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId]);

        $response = $this->handler('')->handle($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertCount(1, $this->mailer->sent);
    }
}
