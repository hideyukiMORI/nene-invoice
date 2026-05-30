<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\PaymentExceedsOutstandingException;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\ServiceApi\RecordServicePaymentHandler;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RecordServicePaymentHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryPaymentRepository $payments;
    private RecordServicePaymentHandler $handler;
    private int $invoiceId;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $holder = new \Nene2\Http\RequestScopedHolder();
        $holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($holder);
        $this->payments = new InMemoryPaymentRepository($holder);

        $this->invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Issued,
            subtotalCents: 2200,
            taxCents: 0,
            totalCents: 2200,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-04-01 00:00:00',
        ));

        $this->handler = new RecordServicePaymentHandler(
            new RecordPaymentUseCase($this->payments, $this->invoices, new RecordingAuditRecorder(), $holder),
            new JsonResponseFactory($this->psr17, $this->psr17),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('POST', '/api/invoices/' . $this->invoiceId . '/payments')
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $this->invoiceId])
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['write:payments']])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));

        return $request;
    }

    public function test_records_payment_and_advances_status(): void
    {
        $response = $this->handler->handle($this->request([
            'amount_cents' => 800,
            'paid_at' => '2026-04-20',
            'method' => 'bank_transfer',
            'external_reference' => 'clear:recon:777',
            'idempotency_key' => 'clear:recon:777:v1',
        ]))
        ;

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertSame(800, $body['total_paid_cents']);
        self::assertSame('clear:recon:777', $body['payment']['external_reference']);
        self::assertSame('partially_paid', $body['invoice']['status']);
        self::assertSame(1400, $body['invoice']['outstanding_cents']);
    }

    public function test_idempotent_replay_does_not_double_post(): void
    {
        $body = ['amount_cents' => 800, 'paid_at' => '2026-04-20', 'idempotency_key' => 'k1'];
        $this->handler->handle($this->request($body));
        $this->handler->handle($this->request($body));

        self::assertSame(800, $this->payments->totalPaidForInvoice($this->invoiceId));
    }

    public function test_missing_idempotency_key_is_422(): void
    {
        $response = $this->handler->handle($this->request(['amount_cents' => 800, 'paid_at' => '2026-04-20']));
        self::assertSame(422, $response->getStatusCode());
    }

    public function test_missing_paid_at_is_422(): void
    {
        $response = $this->handler->handle($this->request(['amount_cents' => 800, 'idempotency_key' => 'k1']));
        self::assertSame(422, $response->getStatusCode());
    }

    public function test_over_allocation_throws_exceeds_outstanding(): void
    {
        $this->expectException(PaymentExceedsOutstandingException::class);
        $this->handler->handle($this->request([
            'amount_cents' => 9999,
            'paid_at' => '2026-04-20',
            'idempotency_key' => 'k2',
        ]));
    }
}
