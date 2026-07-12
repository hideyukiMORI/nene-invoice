<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceEmailException;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\Pdf\InvoicePdfData;
use NeneInvoice\Invoice\Pdf\InvoicePdfGeneratorInterface;
use NeneInvoice\Invoice\SendInvoiceEmailUseCase;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use NeneInvoice\Tests\Support\RecordingMailer;
use PHPUnit\Framework\TestCase;

final class SendInvoiceEmailUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryClientRepository $clients;
    private InMemoryCompanySettingsRepository $companySettings;
    private InMemoryLineItemRepository $lineItems;
    private RecordingMailer $mailer;
    private RecordingAuditRecorder $audit;
    private SendInvoiceEmailUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder          = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices        = new InMemoryInvoiceRepository($this->holder);
        $this->clients         = new InMemoryClientRepository($this->holder);
        $this->companySettings = new InMemoryCompanySettingsRepository();
        $this->lineItems       = new InMemoryLineItemRepository();
        $this->mailer          = new RecordingMailer();
        $this->audit           = new RecordingAuditRecorder();

        // Stub PDF generator: returns fixed bytes without calling mPDF.
        $pdfGenerator = new class () implements InvoicePdfGeneratorInterface {
            public function generate(InvoicePdfData $data): string
            {
                return '%PDF-1.4 fake';
            }
        };

        $this->useCase = new SendInvoiceEmailUseCase(
            $this->invoices,
            $this->lineItems,
            $this->clients,
            $this->companySettings,
            $pdfGenerator,
            $this->mailer,
            $this->audit,
            $this->holder,
            'NeNe Invoice',
        );
    }

    private function issuedInvoice(int $clientId): int
    {
        return $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: $clientId,
            status: InvoiceStatus::Issued,
            subtotalCents: 10000,
            taxCents: 1000,
            totalCents: 11000,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-01 00:00:00',
        ));
    }

    public function test_sends_email_with_pdf_attachment(): void
    {
        $clientId = $this->clients->save(new Client(
            organizationId: 1,
            name: '株式会社サンプル',
            email: 'buyer@example.com',
        ));
        $invoiceId = $this->issuedInvoice($clientId);

        $this->useCase->execute(7, $invoiceId);

        self::assertCount(1, $this->mailer->sent);
        $message = $this->mailer->sent[0];
        self::assertSame('buyer@example.com', $message->toAddress);
        self::assertSame('株式会社サンプル', $message->toName);
        self::assertStringContainsString('INV-2026-001', $message->subject);
        self::assertNotNull($message->attachmentBytes);
        self::assertStringContainsString('INV-2026-001', $message->attachmentName ?? '');
    }

    public function test_records_audit_log_on_send(): void
    {
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: 'Buyer',
            email: 'buyer@example.com',
        ));
        $invoiceId = $this->issuedInvoice($clientId);

        $this->useCase->execute(7, $invoiceId);

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame(7, $record['actor_user_id']);
        self::assertSame(1, $record['organization_id']);
        self::assertSame('invoice.sent', $record['action']);
        self::assertSame('invoice', $record['entity_type']);
        self::assertSame($invoiceId, $record['entity_id']);
        self::assertNull($record['before']);
        self::assertIsArray($record['after']);
        self::assertSame('INV-2026-001', $record['after']['invoice_number']);
    }

    public function test_does_not_record_audit_when_send_fails(): void
    {
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: 'No Email Corp',
        ));
        $invoiceId = $this->issuedInvoice($clientId);

        try {
            $this->useCase->execute(7, $invoiceId);
            self::fail('Expected InvoiceEmailException');
        } catch (InvoiceEmailException) {
            // expected
        }

        self::assertCount(0, $this->audit->records);
    }

    public function test_throws_not_found_for_unknown_invoice(): void
    {
        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute(7, 999);
    }

    public function test_throws_when_client_has_no_email(): void
    {
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: 'No Email Corp',
        ));
        $invoiceId = $this->issuedInvoice($clientId);

        $this->expectException(InvoiceEmailException::class);
        $this->useCase->execute(7, $invoiceId);
    }

    public function test_throws_when_invoice_is_draft(): void
    {
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: 'Buyer',
            email: 'buyer@example.com',
        ));
        $invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: $clientId,
            status: InvoiceStatus::Draft,
            subtotalCents: 1000,
            taxCents: 100,
            totalCents: 1100,
        ));

        $this->expectException(InvoiceEmailException::class);
        $this->useCase->execute(7, $invoiceId);
    }

    public function test_preview_returns_recipient_subject_and_body_without_sending(): void
    {
        $this->companySettings->save(new CompanySettings(
            organizationId: 1,
            legalName: 'テスト商会',
        ));
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: '株式会社サンプル',
            email: 'buyer@example.com',
        ));
        $invoiceId = $this->issuedInvoice($clientId);

        $preview = $this->useCase->preview($invoiceId);

        self::assertSame('buyer@example.com', $preview->recipient);
        self::assertStringContainsString('INV-2026-001', $preview->subject);
        self::assertStringContainsString('テスト商会', $preview->subject);
        self::assertStringContainsString('株式会社サンプル', $preview->bodyHtml);
        self::assertStringContainsString('INV-2026-001', $preview->bodyHtml);

        // Preview must not send a message nor record an audit event.
        self::assertCount(0, $this->mailer->sent);
        self::assertCount(0, $this->audit->records);
    }

    public function test_preview_throws_not_found_for_unknown_invoice(): void
    {
        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->preview(999);
    }

    public function test_preview_throws_when_invoice_is_draft(): void
    {
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: 'Buyer',
            email: 'buyer@example.com',
        ));
        $invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: $clientId,
            status: InvoiceStatus::Draft,
            subtotalCents: 1000,
            taxCents: 100,
            totalCents: 1100,
        ));

        $this->expectException(InvoiceEmailException::class);
        $this->useCase->preview($invoiceId);
    }

    public function test_preview_throws_when_client_has_no_email(): void
    {
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: 'No Email Corp',
        ));
        $invoiceId = $this->issuedInvoice($clientId);

        $this->expectException(InvoiceEmailException::class);
        $this->useCase->preview($invoiceId);
    }

    public function test_uses_company_name_from_settings(): void
    {
        $this->companySettings->save(new CompanySettings(
            organizationId: 1,
            legalName: 'テスト商会',
        ));
        $clientId  = $this->clients->save(new Client(
            organizationId: 1,
            name: 'Buyer',
            email: 'buyer@example.com',
        ));
        $invoiceId = $this->issuedInvoice($clientId);

        $this->useCase->execute(7, $invoiceId);

        $message = $this->mailer->sent[0];
        self::assertStringContainsString('テスト商会', $message->subject);
    }
}
