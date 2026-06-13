<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\PaymentLink;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\PaymentLink\PaymentLink;
use NeneInvoice\PaymentLink\PaymentLinkStatus;
use NeneInvoice\PaymentLink\RecordSettlementUseCase;
use NeneInvoice\PaymentLink\SettlementOutcome;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentLinkRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class RecordSettlementUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    private InMemoryInvoiceRepository $invoices;

    private InMemoryPaymentRepository $payments;

    private InMemoryPaymentLinkRepository $links;

    private RecordSettlementUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->payments = new InMemoryPaymentRepository($this->holder);
        $this->links    = new InMemoryPaymentLinkRepository(1);
        $recordPayment  = new RecordPaymentUseCase(
            $this->payments,
            $this->invoices,
            new ImmediateTransactionManager(),
            fn () => $this->payments,
            fn () => $this->invoices,
            fn () => new RecordingAuditRecorder(),
            new FixedClock(),
            $this->holder,
        );
        $this->useCase = new RecordSettlementUseCase($this->links, $recordPayment, new FixedClock(), $this->holder);
    }

    private function seedInvoice(InvoiceStatus $status = InvoiceStatus::Issued, int $total = 1000): int
    {
        return $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: $status, subtotalCents: $total, taxCents: 0, totalCents: $total));
    }

    private function seedLink(int $invoiceId, ?string $gatewaySessionId = null): int
    {
        return $this->links->save(new PaymentLink(
            organizationId: 1,
            invoiceId: $invoiceId,
            tokenHash: 'hash-' . $invoiceId,
            gateway: 'payjp',
            status: PaymentLinkStatus::Active,
            expiresAt: '2026-06-13 03:00:00',
            gatewaySessionId: $gatewaySessionId,
            createdAt: '2026-06-06 03:00:00',
            updatedAt: '2026-06-06 03:00:00',
        ));
    }

    public function test_resolves_by_payment_link_id_records_and_marks_paid(): void
    {
        $invoiceId = $this->seedInvoice();
        $linkId    = $this->seedLink($invoiceId);

        $outcome = $this->useCase->execute($linkId, 'ch_1', 1000);

        self::assertSame(SettlementOutcome::Recorded, $outcome);
        self::assertSame(1000, $this->payments->totalPaidForInvoice($invoiceId));
        $link = $this->links->findByIdUnscoped($linkId);
        self::assertNotNull($link);
        self::assertSame(PaymentLinkStatus::Paid, $link->status);
        self::assertSame('ch_1', $link->gatewaySessionId);
    }

    public function test_resolves_by_charge_id_when_no_metadata_link_id(): void
    {
        $invoiceId = $this->seedInvoice();
        $this->seedLink($invoiceId, gatewaySessionId: 'ch_2');

        $outcome = $this->useCase->execute(null, 'ch_2', 1000);

        self::assertSame(SettlementOutcome::Recorded, $outcome);
        self::assertSame(1000, $this->payments->totalPaidForInvoice($invoiceId));
    }

    public function test_replayed_event_is_idempotent(): void
    {
        $invoiceId = $this->seedInvoice();
        $linkId    = $this->seedLink($invoiceId);

        $this->useCase->execute($linkId, 'ch_3', 1000);
        $second = $this->useCase->execute($linkId, 'ch_3', 1000);

        self::assertSame(SettlementOutcome::Recorded, $second);
        // Same charge id → one payment, not two (idempotency on charge id).
        self::assertSame(1000, $this->payments->totalPaidForInvoice($invoiceId));
    }

    public function test_unknown_link_returns_link_not_found(): void
    {
        self::assertSame(SettlementOutcome::LinkNotFound, $this->useCase->execute(999, 'ch_x', 1000));
        self::assertSame(SettlementOutcome::LinkNotFound, $this->useCase->execute(null, 'ch_unknown', 1000));
    }

    public function test_non_recordable_invoice_is_ignored(): void
    {
        $invoiceId = $this->seedInvoice(InvoiceStatus::Draft);
        $linkId    = $this->seedLink($invoiceId);

        $outcome = $this->useCase->execute($linkId, 'ch_4', 1000);

        self::assertSame(SettlementOutcome::Ignored, $outcome);
        self::assertSame(0, $this->payments->totalPaidForInvoice($invoiceId));
    }
}
