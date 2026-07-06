<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\PaymentLink;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\PaymentLink\GeneratePaymentLinkUseCase;
use NeneInvoice\PaymentLink\PaymentLinkStatus;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentLinkRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class GeneratePaymentLinkUseCaseTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private InMemoryPaymentLinkRepository $links;

    private GeneratePaymentLinkUseCase $useCase;

    private RecordingAuditRecorder $audit;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->links    = new InMemoryPaymentLinkRepository(1);
        $this->audit    = new RecordingAuditRecorder();
        $this->useCase  = new GeneratePaymentLinkUseCase($this->invoices, new ImmediateTransactionManager(), fn () => $this->links, $this->audit, new FixedClock(), $this->holder);
    }

    private function newInvoice(int $organizationId = 1): int
    {
        return $this->invoices->save(new Invoice(organizationId: $organizationId, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 100, totalCents: 1100));
    }

    public function test_generates_active_payjp_link_for_invoice_in_org(): void
    {
        $id = $this->newInvoice();

        $result = $this->useCase->execute(7, $id);

        self::assertNotEmpty($result['rawToken']);
        self::assertSame('2026-06-13 03:00:00', $result['expiresAt']);
        self::assertGreaterThan(0, $result['paymentLinkId']);

        // Stored hashed, active, with the launch gateway.
        $stored = $this->links->findByHash(hash('sha256', $result['rawToken']));
        self::assertNotNull($stored);
        self::assertSame($id, $stored->invoiceId);
        self::assertSame(PaymentLinkStatus::Active, $stored->status);
        self::assertSame('payjp', $stored->gateway);
        self::assertNull($stored->gatewaySessionId);
    }

    public function test_records_audit_log_without_leaking_token(): void
    {
        $id = $this->newInvoice();

        $result = $this->useCase->execute(7, $id);

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame(7, $record['actor_user_id']);
        self::assertSame(1, $record['organization_id']);
        self::assertSame('payment_link.issued', $record['action']);
        self::assertSame('payment_link', $record['entity_type']);
        self::assertSame($result['paymentLinkId'], $record['entity_id']);
        self::assertNull($record['before']);
        self::assertSame(['invoice_id' => $id, 'gateway' => 'payjp', 'expires_at' => $result['expiresAt']], $record['after']);

        $encoded = json_encode($record, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($result['rawToken'], $encoded);
        self::assertStringNotContainsString(hash('sha256', $result['rawToken']), $encoded);
    }

    public function test_reissue_auto_revokes_prior_active_link(): void
    {
        $id = $this->newInvoice();

        $first  = $this->useCase->execute(7, $id);
        $second = $this->useCase->execute(7, $id);

        $priorLink = $this->links->findByHash(hash('sha256', $first['rawToken']));
        self::assertNotNull($priorLink);
        self::assertSame(PaymentLinkStatus::Revoked, $priorLink->status, 'prior link must be auto-revoked on re-issue');

        $newLink = $this->links->findByHash(hash('sha256', $second['rawToken']));
        self::assertNotNull($newLink);
        self::assertSame(PaymentLinkStatus::Active, $newLink->status);

        // Exactly one active link remains for the invoice.
        $active = $this->links->findActiveByInvoiceId($id);
        self::assertNotNull($active);
        self::assertSame($newLink->id, $active->id);
    }

    public function test_throws_for_wrong_org(): void
    {
        // Invoice belongs to org 2; the holder resolves org 1, so it is invisible.
        $id = $this->newInvoice(2);

        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute(7, $id);
    }

    public function test_raw_token_is_url_safe_base64(): void
    {
        $id     = $this->newInvoice();
        $result = $this->useCase->execute(7, $id);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $result['rawToken']);
    }
}
