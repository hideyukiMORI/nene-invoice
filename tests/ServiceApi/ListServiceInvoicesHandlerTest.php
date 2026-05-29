<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\ListInvoicesUseCase;
use NeneInvoice\Payment\Payment;
use NeneInvoice\ServiceApi\ListServiceInvoicesHandler;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ListServiceInvoicesHandlerTest extends TestCase
{
    public function test_returns_service_read_model_with_outstanding_and_currency(): void
    {
        $invoices = new InMemoryInvoiceRepository();
        $payments = new InMemoryPaymentRepository();

        $id = $invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::PartiallyPaid,
            subtotalCents: 2200,
            taxCents: 0,
            totalCents: 2200,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-30 00:00:00',
        ));
        $payments->save(new Payment(organizationId: 1, invoiceId: $id, amountCents: 800, paidAt: '2026-05-30 10:00:00'));

        $psr17 = new Psr17Factory();
        $handler = new ListServiceInvoicesHandler(
            new ListInvoicesUseCase($invoices, $payments),
            new JsonResponseFactory($psr17, $psr17),
            new ProblemDetailsResponseFactory($psr17, $psr17, 'https://nene-invoice.dev/problems/'),
        );

        $request = $psr17->createServerRequest('GET', '/api/invoices')
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['read:invoices']]);

        $response = $handler->handle($request);
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertIsArray($body['items']);
        $item = $body['items'][0];
        self::assertIsArray($item);
        self::assertSame($id, $item['invoice_id']);
        self::assertSame(1400, $item['outstanding_cents']); // 2200 − 800
        self::assertSame('JPY', $item['currency']);
        self::assertSame('partially_paid', $item['status']);
    }

    public function test_rejects_token_without_organization(): void
    {
        $psr17 = new Psr17Factory();
        $handler = new ListServiceInvoicesHandler(
            new ListInvoicesUseCase(new InMemoryInvoiceRepository(), new InMemoryPaymentRepository()),
            new JsonResponseFactory($psr17, $psr17),
            new ProblemDetailsResponseFactory($psr17, $psr17, 'https://nene-invoice.dev/problems/'),
        );

        $request = $psr17->createServerRequest('GET', '/api/invoices')
            ->withAttribute('nene2.auth.claims', ['scopes' => ['read:invoices']]);

        self::assertSame(403, $handler->handle($request)->getStatusCode());
    }

    public function test_status_query_filter_narrows_results(): void
    {
        $invoices = new InMemoryInvoiceRepository();
        $invoices->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Paid, subtotalCents: 1000, taxCents: 0, totalCents: 1000, invoiceNumber: 'INV-PAID', issuedAt: '2026-01-01 00:00:00'));
        $invoices->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Issued, subtotalCents: 2000, taxCents: 0, totalCents: 2000, invoiceNumber: 'INV-OPEN', issuedAt: '2026-02-01 00:00:00'));

        $psr17 = new Psr17Factory();
        $handler = new ListServiceInvoicesHandler(
            new ListInvoicesUseCase($invoices, new InMemoryPaymentRepository()),
            new JsonResponseFactory($psr17, $psr17),
            new ProblemDetailsResponseFactory($psr17, $psr17, 'https://nene-invoice.dev/problems/'),
        );

        $request = $psr17->createServerRequest('GET', '/api/invoices', ['status' => 'issued'])
            ->withQueryParams(['status' => 'issued'])
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['read:invoices']]);

        $body = json_decode((string) $handler->handle($request)->getBody(), true);
        self::assertIsArray($body);
        self::assertSame(1, $body['total']);
        self::assertIsArray($body['items']);
        self::assertCount(1, $body['items']);
        self::assertSame('INV-OPEN', $body['items'][0]['invoice_number']);
    }
}
