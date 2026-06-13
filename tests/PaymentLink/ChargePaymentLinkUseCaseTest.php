<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\PaymentLink;

use Nene2\Http\RequestScopedHolder;
use Nene2\Http\SecureTokenHelper;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\Gateway\PaymentGatewayException;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\PaymentLink\ChargePaymentLinkUseCase;
use NeneInvoice\PaymentLink\PaymentLink;
use NeneInvoice\PaymentLink\PaymentLinkNotPayableException;
use NeneInvoice\PaymentLink\PaymentLinkStatus;
use NeneInvoice\Tests\Support\FakeGateway;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentLinkRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ChargePaymentLinkUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    private InMemoryInvoiceRepository $invoices;

    private InMemoryPaymentRepository $payments;

    private InMemoryPaymentLinkRepository $links;

    private RecordPaymentUseCase $recordPayment;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->payments = new InMemoryPaymentRepository($this->holder);
        $this->links    = new InMemoryPaymentLinkRepository(1);
        $audit          = new RecordingAuditRecorder();
        $this->recordPayment = new RecordPaymentUseCase(
            $this->payments,
            $this->invoices,
            new ImmediateTransactionManager(),
            fn () => $this->payments,
            fn () => $this->invoices,
            fn () => $audit,
            new FixedClock(),
            $this->holder,
        );
    }

    private function useCase(FakeGateway $gateway): ChargePaymentLinkUseCase
    {
        return new ChargePaymentLinkUseCase(
            $this->links,
            $this->invoices,
            $this->payments,
            $gateway,
            $this->recordPayment,
            new FixedClock(),
            $this->holder,
        );
    }

    private function seedInvoice(int $totalCents = 1000, InvoiceStatus $status = InvoiceStatus::Issued): int
    {
        return $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: $status, subtotalCents: $totalCents, taxCents: 0, totalCents: $totalCents));
    }

    private function seedLink(int $invoiceId, string $rawToken, PaymentLinkStatus $status = PaymentLinkStatus::Active): int
    {
        return $this->links->save(new PaymentLink(
            organizationId: 1,
            invoiceId: $invoiceId,
            tokenHash: SecureTokenHelper::hash($rawToken),
            gateway: 'payjp',
            status: $status,
            expiresAt: '2026-06-13 03:00:00',
            createdAt: '2026-06-06 03:00:00',
            updatedAt: '2026-06-06 03:00:00',
        ));
    }

    public function test_charges_outstanding_records_payment_and_marks_link_paid(): void
    {
        $invoiceId = $this->seedInvoice(1000);
        $this->seedLink($invoiceId, 'raw-1');
        $gateway = new FakeGateway('ch_test_1');

        $result = $this->useCase($gateway)->execute('raw-1', 'tok_card');

        self::assertSame('ch_test_1', $result->chargeId);
        self::assertSame(1000, $result->amountCents);
        self::assertSame('paid', $result->invoiceStatus);

        // Gateway received the outstanding amount, jpy, and our metadata.
        self::assertNotNull($gateway->lastRequest);
        self::assertSame(1000, $gateway->lastRequest->amountCents);
        self::assertSame('jpy', $gateway->lastRequest->currency);
        self::assertSame('tok_card', $gateway->lastRequest->cardToken);
        self::assertSame((string) $invoiceId, $gateway->lastRequest->metadata['invoice_id']);
        self::assertArrayHasKey('payment_link_id', $gateway->lastRequest->metadata);

        // Payment recorded as a card payment keyed by the charge id.
        self::assertSame(1000, $this->payments->totalPaidForInvoice($invoiceId));
        $payment = $this->payments->findByIdempotencyKey('ch_test_1');
        self::assertNotNull($payment);
        self::assertSame('card', $payment->method);
        self::assertSame('ch_test_1', $payment->externalReference);

        // Link marked paid with the charge id.
        $link = $this->links->findByHash(SecureTokenHelper::hash('raw-1'));
        self::assertNotNull($link);
        self::assertSame(PaymentLinkStatus::Paid, $link->status);
        self::assertSame('ch_test_1', $link->gatewaySessionId);
    }

    public function test_charges_only_the_outstanding_balance(): void
    {
        $invoiceId = $this->seedInvoice(1000, InvoiceStatus::PartiallyPaid);
        // 400 already paid → outstanding 600.
        $this->payments->save(new Payment(organizationId: 1, invoiceId: $invoiceId, amountCents: 400, paidAt: '2026-06-06 03:00:00'));
        $this->seedLink($invoiceId, 'raw-2');
        $gateway = new FakeGateway('ch_test_2');

        $result = $this->useCase($gateway)->execute('raw-2', 'tok_card');

        self::assertNotNull($gateway->lastRequest);
        self::assertSame(600, $gateway->lastRequest->amountCents, 'must charge only the outstanding balance');
        self::assertSame(600, $result->amountCents);
        self::assertSame('paid', $result->invoiceStatus);
    }

    public function test_revoked_link_is_not_payable(): void
    {
        $invoiceId = $this->seedInvoice(1000);
        $this->seedLink($invoiceId, 'raw-3', PaymentLinkStatus::Revoked);

        $this->expectException(PaymentLinkNotPayableException::class);
        $this->useCase(new FakeGateway())->execute('raw-3', 'tok_card');
    }

    public function test_unknown_token_is_not_payable(): void
    {
        $this->expectException(PaymentLinkNotPayableException::class);
        $this->useCase(new FakeGateway())->execute('no-such-token', 'tok_card');
    }

    public function test_declined_charge_records_no_payment_and_leaves_link_active(): void
    {
        $invoiceId = $this->seedInvoice(1000);
        $this->seedLink($invoiceId, 'raw-4');
        $gateway = new FakeGateway('ch_x', 'Your card was declined.');

        try {
            $this->useCase($gateway)->execute('raw-4', 'tok_card');
            self::fail('expected PaymentGatewayException');
        } catch (PaymentGatewayException) {
            // expected
        }

        self::assertSame(0, $this->payments->totalPaidForInvoice($invoiceId), 'no payment on decline');
        $link = $this->links->findByHash(SecureTokenHelper::hash('raw-4'));
        self::assertNotNull($link);
        self::assertSame(PaymentLinkStatus::Active, $link->status, 'link stays active on decline');
    }
}
