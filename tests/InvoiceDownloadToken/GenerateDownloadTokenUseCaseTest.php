<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\InvoiceDownloadToken;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\InvoiceDownloadToken\GenerateDownloadTokenUseCase;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\InMemoryInvoiceDownloadTokenRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class GenerateDownloadTokenUseCaseTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private InMemoryInvoiceDownloadTokenRepository $tokens;

    private GenerateDownloadTokenUseCase $useCase;

    private RecordingAuditRecorder $audit;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->holder   = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->tokens   = new InMemoryInvoiceDownloadTokenRepository();
        $this->audit    = new RecordingAuditRecorder();
        $this->useCase  = new GenerateDownloadTokenUseCase($this->invoices, $this->tokens, $this->audit, new FixedClock(), $this->holder);
    }

    public function test_generates_token_for_invoice_in_org(): void
    {
        $id = $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 100, totalCents: 1100));

        $result = $this->useCase->execute(7, $id);

        self::assertNotEmpty($result['rawToken']);
        self::assertNotEmpty($result['expiresAt']);

        // Token is stored hashed
        $stored = $this->tokens->findByHash(hash('sha256', $result['rawToken']));
        self::assertNotNull($stored);
        self::assertSame($id, $stored->invoiceId);
    }

    public function test_records_audit_log_without_leaking_token(): void
    {
        $id = $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 100, totalCents: 1100));

        $result = $this->useCase->execute(7, $id);

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame(7, $record['actor_user_id']);
        self::assertSame(1, $record['organization_id']);
        self::assertSame('invoice.download_token_issued', $record['action']);
        self::assertSame('invoice', $record['entity_type']);
        self::assertSame($id, $record['entity_id']);
        self::assertNull($record['before']);
        self::assertSame(['expires_at' => $result['expiresAt']], $record['after']);

        // The raw token and its hash must never reach the audit trail.
        $encoded = json_encode($record, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($result['rawToken'], $encoded);
        self::assertStringNotContainsString(hash('sha256', $result['rawToken']), $encoded);
    }

    public function test_throws_for_wrong_org(): void
    {
        // Invoice belongs to org 2; the holder resolves org 1, so it is invisible.
        $id = $this->invoices->save(new Invoice(organizationId: 2, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000));

        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute(7, $id);
    }

    public function test_raw_token_is_url_safe_base64(): void
    {
        $id     = $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000));
        $result = $this->useCase->execute(7, $id);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $result['rawToken']);
    }
}
