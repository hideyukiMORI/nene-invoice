<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Dashboard;

use NeneInvoice\Dashboard\GetDashboardSummaryUseCase;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use PHPUnit\Framework\TestCase;

final class GetDashboardSummaryUseCaseTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private GetDashboardSummaryUseCase $useCase;

    protected function setUp(): void
    {
        $holder = new \Nene2\Http\RequestScopedHolder();
        $holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($holder);
        $this->useCase  = new GetDashboardSummaryUseCase($this->invoices, new InMemoryPaymentRepository($holder));
    }

    public function test_empty_organization_returns_zeros(): void
    {
        $summary = $this->useCase->execute();

        self::assertSame(0, $summary->unpaidCount);
        self::assertSame(0, $summary->overdueCount);
        self::assertSame(0, $summary->outstandingTotalCents);
        self::assertCount(0, $summary->recentUnpaid);
    }

    public function test_counts_unpaid_invoices(): void
    {
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000));
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::PartiallyPaid, subtotalCents: 2000, taxCents: 0, totalCents: 2000));
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Paid, subtotalCents: 500, taxCents: 0, totalCents: 500));
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Draft, subtotalCents: 500, taxCents: 0, totalCents: 500));

        $summary = $this->useCase->execute();

        self::assertSame(2, $summary->unpaidCount);
        self::assertCount(2, $summary->recentUnpaid);
    }

    public function test_counts_overdue_invoices(): void
    {
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000, dueAt: '2020-01-01 00:00:00'));
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 2000, taxCents: 0, totalCents: 2000, dueAt: '2099-12-31 23:59:59'));

        $summary = $this->useCase->execute();

        self::assertSame(2, $summary->unpaidCount);
        self::assertSame(1, $summary->overdueCount);
    }

    public function test_isolates_organizations(): void
    {
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000));
        $this->invoices->save(new Invoice(organizationId: 2, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 2000, taxCents: 0, totalCents: 2000));

        $summary = $this->useCase->execute();

        self::assertSame(1, $summary->unpaidCount);
    }

    public function test_recent_unpaid_capped_at_five(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000));
        }

        $summary = $this->useCase->execute();

        self::assertSame(7, $summary->unpaidCount);
        self::assertCount(5, $summary->recentUnpaid);
    }
}
