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

    private InMemoryPaymentRepository $payments;

    private GetDashboardSummaryUseCase $useCase;

    protected function setUp(): void
    {
        $holder = new \Nene2\Http\RequestScopedHolder();
        $holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($holder);
        $this->payments = new InMemoryPaymentRepository($holder);
        $this->useCase  = new GetDashboardSummaryUseCase($this->invoices, $this->payments);
    }

    public function test_empty_organization_returns_zeros(): void
    {
        $summary = $this->useCase->execute();

        self::assertSame(0, $summary->unpaidCount);
        self::assertSame(0, $summary->overdueCount);
        self::assertSame(0, $summary->outstandingTotalCents);
        self::assertCount(0, $summary->recentUnpaid);
        self::assertSame(0, $summary->receivedThisMonthCents);
        self::assertSame(0, $summary->receivedLastMonthCents);
        self::assertSame(['current' => 0, 'overdue_1_30' => 0, 'overdue_31_plus' => 0], $summary->aging);
        self::assertSame(0, $summary->billedThisMonthCents);
        self::assertSame(0, $summary->billedLastMonthCents);
        self::assertCount(6, $summary->monthlyBilled);
        self::assertSame(0, $summary->monthlyBilled[5]['billed_cents']);
        self::assertSame(0, $summary->billedPrevYearMonthCents);
        self::assertNotEmpty($summary->billedDailyCurrent);
        self::assertSame(1, $summary->billedDailyCurrent[0]['day']);
        self::assertSame(0, $summary->billedDailyCurrent[0]['cumulative_cents']);
    }

    public function test_year_over_year_and_daily_cumulative(): void
    {
        $issue = function (string $issuedAt, int $cents): void {
            $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: $cents, taxCents: 0, totalCents: $cents, issuedAt: $issuedAt));
        };
        $issue(date('Y-m-01 10:00:00'), 3000); // this month, day 1
        $issue(date('Y-m-01 10:00:00', (int) strtotime('first day of last month')), 4000); // last month, day 1
        $issue(date('Y-m-01 10:00:00', (int) strtotime('-1 year')), 5000); // same month last year, day 1

        $summary = $this->useCase->execute();

        self::assertSame(5000, $summary->billedPrevYearMonthCents);

        // Day-1 invoices: cumulative is constant from day 1 onward.
        self::assertSame(3000, $summary->billedDailyCurrent[0]['cumulative_cents']);
        self::assertSame(4000, $summary->billedDailyPrevMonth[0]['cumulative_cents']);
    }

    public function test_billed_metrics_and_monthly_trend(): void
    {
        $thisMonth = date('Y-m-10 09:00:00');
        $lastMonth = date('Y-m-10 09:00:00', (int) strtotime('first day of last month'));

        // Issued this month: 1000 + 2000 (paid still counts as issued).
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000, issuedAt: $thisMonth));
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Paid, subtotalCents: 2000, taxCents: 0, totalCents: 2000, issuedAt: $thisMonth));
        // Issued last month: 5000.
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 5000, taxCents: 0, totalCents: 5000, issuedAt: $lastMonth));
        // Draft (never issued) is excluded.
        $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Draft, subtotalCents: 9999, taxCents: 0, totalCents: 9999));

        $summary = $this->useCase->execute();

        self::assertSame(3000, $summary->billedThisMonthCents);
        self::assertSame(5000, $summary->billedLastMonthCents);

        self::assertCount(6, $summary->monthlyBilled);
        // Last bucket is the current month (oldest→newest).
        self::assertSame(date('Y-m'), $summary->monthlyBilled[5]['month']);
        self::assertSame(3000, $summary->monthlyBilled[5]['billed_cents']);
        self::assertSame(2, $summary->monthlyBilled[5]['count']);
        self::assertSame(5000, $summary->monthlyBilled[4]['billed_cents']);
        self::assertSame(1, $summary->monthlyBilled[4]['count']);
    }

    public function test_received_this_month_sums_current_month_payments(): void
    {
        $thisMonth = date('Y-m-15 12:00:00');
        $this->payments->save(new \NeneInvoice\Payment\Payment(organizationId: 1, invoiceId: 1, amountCents: 4200, paidAt: $thisMonth));

        $summary = $this->useCase->execute();

        self::assertSame(4200, $summary->receivedThisMonthCents);
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
