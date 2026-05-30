<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Dashboard;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Dashboard\GetDashboardHandler;
use NeneInvoice\Dashboard\GetDashboardSummaryUseCase;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class GetDashboardHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryInvoiceRepository $invoices;
    private InMemoryPaymentRepository $payments;
    private GetDashboardHandler $handler;

    protected function setUp(): void
    {
        $this->psr17    = new Psr17Factory();
        $holder         = new RequestScopedHolder();
        $holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($holder);
        $this->payments = new InMemoryPaymentRepository($holder);

        $this->handler = new GetDashboardHandler(
            new GetDashboardSummaryUseCase($this->invoices, $this->payments),
            $this->payments,
            new JsonResponseFactory($this->psr17, $this->psr17),
        );
    }

    public function test_returns_summary_json_for_empty_org(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/admin/dashboard')
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'admin', 'org' => 1]);

        $response = $this->handler->handle($request);
        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(0, $body['unpaid_count']);
        self::assertSame(0, $body['overdue_count']);
        self::assertSame(0, $body['outstanding_total_cents']);
        self::assertSame([], $body['recent_unpaid']);
    }

    public function test_returns_correct_counts_with_invoices(): void
    {
        $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 1000,
            taxCents: 0,
            totalCents: 1000,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-01 00:00:00',
            dueAt: '2020-01-01 00:00:00', // overdue
        ));
        $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::PartiallyPaid,
            subtotalCents: 2000,
            taxCents: 0,
            totalCents: 2000,
            invoiceNumber: 'INV-2026-002',
            issuedAt: '2026-05-01 00:00:00',
        ));
        $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Paid,
            subtotalCents: 500,
            taxCents: 0,
            totalCents: 500,
        ));

        $request = $this->psr17->createServerRequest('GET', '/admin/dashboard')
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'admin', 'org' => 1]);

        $response = $this->handler->handle($request);
        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['unpaid_count']);
        self::assertSame(1, $body['overdue_count']);
        self::assertCount(2, $body['recent_unpaid']);
    }

    // Note: the "no org context" case is now handled upstream by
    // OrgResolverMiddleware (404), not by this handler (ADR 0006).
}
