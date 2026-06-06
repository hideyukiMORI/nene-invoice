<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use DateTimeImmutable;
use Nene2\Http\ClockInterface;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use NeneInvoice\Support\Jst;

/**
 * Assembles the dashboard summary: unpaid/overdue counts, outstanding balance,
 * and the most recent 5 unpaid invoices. Both repositories are org-scoped via
 * the request holder, so no organization id is threaded here (ADR 0006).
 */
final readonly class GetDashboardSummaryUseCase implements GetDashboardSummaryUseCaseInterface
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private ClockInterface $clock,
    ) {
    }

    public function execute(): DashboardSummary
    {
        // Months are Japanese calendar months: boundaries are computed on the JST
        // wall clock, then converted to the UTC values stored in instant columns
        // (issued_at, paid_at) for range queries. The overdue check compares the
        // JST-calendar due_at against the current JST day. (ADR 0010)
        $nowJst = Jst::of($this->clock->now());
        $now    = $nowJst->format('Y-m-d H:i:s');
        $data   = $this->invoices->getDashboardData($now);

        $outstandingTotalCents = $this->payments->outstandingTotal();

        $firstThisMonthJst = $nowJst->modify('first day of this month')->setTime(0, 0);
        $thisMonthStart = Jst::toUtcString($firstThisMonthJst);
        $nextMonthStart = Jst::toUtcString($firstThisMonthJst->modify('+1 month'));
        $lastMonthStart = Jst::toUtcString($firstThisMonthJst->modify('-1 month'));
        // Aging compares the JST-calendar due_at, so its boundary stays in JST.
        $thirtyDaysAgo  = $nowJst->modify('-30 days')->format('Y-m-d H:i:s');

        $billedThisMonth = $this->invoices->billedTotalBetween($thisMonthStart, $nextMonthStart);
        $billedLastMonth = $this->invoices->billedTotalBetween($lastMonthStart, $thisMonthStart);

        // Prior-year same month (YoY hero).
        $prevYearStart = $firstThisMonthJst->modify('-1 year');
        $billedPrevYearMonth = $this->invoices->billedTotalBetween(
            Jst::toUtcString($prevYearStart),
            Jst::toUtcString($prevYearStart->modify('+1 month')),
        );

        // Daily cumulative pace — current month (1..today) and the full prior month.
        $today = (int) $nowJst->format('j');
        $prevMonthLen = (int) $firstThisMonthJst->modify('-1 month')->format('t');
        $dailyCurrent = $this->dailyCumulative(
            $this->invoices->billedRowsBetween($thisMonthStart, $nextMonthStart),
            $today,
        );
        $dailyPrev = $this->dailyCumulative(
            $this->invoices->billedRowsBetween($lastMonthStart, $thisMonthStart),
            $prevMonthLen,
        );

        return new DashboardSummary(
            unpaidCount: $data['unpaid_count'],
            overdueCount: $data['overdue_count'],
            outstandingTotalCents: $outstandingTotalCents,
            recentUnpaid: $data['recent_unpaid'],
            receivedThisMonthCents: $this->payments->receivedTotalBetween($thisMonthStart, $nextMonthStart),
            receivedLastMonthCents: $this->payments->receivedTotalBetween($lastMonthStart, $thisMonthStart),
            aging: $this->payments->agingBuckets($now, $thirtyDaysAgo),
            billedThisMonthCents: $billedThisMonth['cents'],
            billedLastMonthCents: $billedLastMonth['cents'],
            monthlyBilled: $this->monthlyBilled($firstThisMonthJst),
            billedPrevYearMonthCents: $billedPrevYearMonth['cents'],
            billedDailyCurrent: $dailyCurrent,
            billedDailyPrevMonth: $dailyPrev,
        );
    }

    /**
     * Builds a per-day cumulative series from issued-invoice rows. Date grouping
     * is in PHP (dialect-agnostic). Days are 1..$daysCount inclusive.
     *
     * @param list<array{issued_at: string, total_cents: int}> $rows
     *
     * @return list<array{day: int, cumulative_cents: int}>
     */
    private function dailyCumulative(array $rows, int $daysCount): array
    {
        $perDay = array_fill(1, max($daysCount, 1), 0);

        foreach ($rows as $row) {
            // issued_at is stored in UTC; bucket by the JST calendar day.
            $day = (int) Jst::of(new DateTimeImmutable($row['issued_at']))->format('j');
            if ($day <= $daysCount) {
                $perDay[$day] += $row['total_cents'];
            }
        }

        $series = [];
        $cumulative = 0;
        for ($d = 1; $d <= $daysCount; ++$d) {
            $cumulative += $perDay[$d];
            $series[] = ['day' => $d, 'cumulative_cents' => $cumulative];
        }

        return $series;
    }

    /**
     * Issued-invoice totals for the last 6 JST calendar months (oldest→newest).
     * Month boundaries are JST wall-clock times converted to the UTC issued_at
     * values used by the range query (ADR 0010).
     *
     * @param DateTimeImmutable $firstThisMonthJst first day of this JST month (00:00 JST)
     *
     * @return list<array{month: string, billed_cents: int, count: int}>
     */
    private function monthlyBilled(DateTimeImmutable $firstThisMonthJst): array
    {
        $months = [];

        for ($k = 5; $k >= 0; --$k) {
            $start  = $firstThisMonthJst->modify("-{$k} month");
            $end    = $start->modify('+1 month');
            $bucket = $this->invoices->billedTotalBetween(
                Jst::toUtcString($start),
                Jst::toUtcString($end),
            );

            $months[] = [
                'month'        => $start->format('Y-m'),
                'billed_cents' => $bucket['cents'],
                'count'        => $bucket['count'],
            ];
        }

        return $months;
    }
}
