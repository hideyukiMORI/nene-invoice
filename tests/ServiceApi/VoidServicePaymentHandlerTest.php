<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Payment\VoidPaymentUseCase;
use NeneInvoice\ServiceApi\VoidServicePaymentHandler;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class VoidServicePaymentHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    /** @var \Nene2\Http\RequestScopedHolder<int> */
    private \Nene2\Http\RequestScopedHolder $holder;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryPaymentRepository $payments;
    private VoidServicePaymentHandler $handler;
    private int $invoiceId;
    private int $paymentId;

    protected function setUp(): void
    {
        $this->psr17    = new Psr17Factory();
        $this->holder = new \Nene2\Http\RequestScopedHolder();
        $this->holder->set(1);
        $holder = $this->holder;
        $this->invoices = new InMemoryInvoiceRepository($holder);
        $this->payments = new InMemoryPaymentRepository($holder);

        $this->invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Paid,
            subtotalCents: 1000,
            taxCents: 0,
            totalCents: 1000,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-01 00:00:00',
        ));
        $this->paymentId = $this->payments->save(new Payment(
            organizationId: 1,
            invoiceId: $this->invoiceId,
            amountCents: 1000,
            paidAt: '2026-05-10 00:00:00',
            externalReference: 'clear-ref-001',
        ));

        $this->handler = new VoidServicePaymentHandler(
            new VoidPaymentUseCase($this->payments, $this->invoices, new ImmediateTransactionManager(), fn () => $this->payments, fn () => $this->invoices, new RecordingAuditRecorder(), $holder),
            new JsonResponseFactory($this->psr17, $this->psr17),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    public function test_voids_payment_and_returns_json(): void
    {
        $request = $this->psr17->createServerRequest('POST', "/api/invoices/{$this->invoiceId}/payments/{$this->paymentId}/void")
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['write:payments']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId, 'paymentId' => (string) $this->paymentId]);

        $response = $this->handler->handle($request);
        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($body['payment']['voided']);
        self::assertSame(0, $body['total_paid_cents']);
    }

    public function test_reason_field_is_optional(): void
    {
        $request = $this->psr17->createServerRequest('POST', "/api/invoices/{$this->invoiceId}/payments/{$this->paymentId}/void")
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['write:payments']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId, 'paymentId' => (string) $this->paymentId])
            ->withBody($this->psr17->createStream('{"reason":"reconciliation reversal"}'));

        $response = $this->handler->handle($request);
        self::assertSame(200, $response->getStatusCode());
    }

    public function test_returns_403_without_org_in_token(): void
    {
        $request = $this->psr17->createServerRequest('POST', '/api/invoices/1/payments/1/void')
            ->withAttribute('nene2.auth.claims', ['scopes' => ['write:payments']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => '1', 'paymentId' => '1']);

        $response = $this->handler->handle($request);
        self::assertSame(403, $response->getStatusCode());
    }

    public function test_throws_payment_not_found_for_cross_org(): void
    {
        // VoidPaymentUseCase throws PaymentNotFoundException for cross-org access.
        // In production this is handled by PaymentNotFoundExceptionHandler. The
        // holder is set by ServiceScopeMiddleware from the token org (here: 2).
        $this->expectException(\NeneInvoice\Payment\PaymentNotFoundException::class);
        $this->holder->set(2);

        $request = $this->psr17->createServerRequest('POST', "/api/invoices/{$this->invoiceId}/payments/{$this->paymentId}/void")
            ->withAttribute('nene2.auth.claims', ['org' => 2, 'scopes' => ['write:payments']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId, 'paymentId' => (string) $this->paymentId]);

        $this->handler->handle($request);
    }

    public function test_idempotent_on_already_voided_payment(): void
    {
        $params = ['id' => (string) $this->invoiceId, 'paymentId' => (string) $this->paymentId];
        $makeReq = fn () => $this->psr17->createServerRequest('POST', '/void')
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['write:payments']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, $params);

        self::assertSame(200, $this->handler->handle($makeReq())->getStatusCode());
        // Second void of the same payment is idempotent
        self::assertSame(200, $this->handler->handle($makeReq())->getStatusCode());
    }
}
