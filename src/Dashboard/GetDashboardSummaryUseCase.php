<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use DateTimeImmutable;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;

/**
 * Assembles the dashboard summary: unpaid/overdue counts, outstanding balance,
 * and the most recent 5 unpaid invoices. Both repositories are org-scoped via
 * the request holder, so no organization id is threaded here (ADR 0006).
 */
final readonly class GetDashboardSummaryUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
    ) {
    }

    public function execute(): DashboardSummary
    {
        // All time boundaries follow the application timezone (deploy with
        // TZ=Asia/Tokyo), consistent with how paid_at / due_at are written.
        $nowDt = new DateTimeImmutable();
        $now   = $nowDt->format('Y-m-d H:i:s');
        $data  = $this->invoices->getDashboardData($now);

        $outstandingTotalCents = $this->payments->outstandingTotal();

        $thisMonthStart = $nowDt->format('Y-m-01 00:00:00');
        $nextMonthStart = $nowDt->modify('first day of next month')->format('Y-m-01 00:00:00');
        $lastMonthStart = $nowDt->modify('first day of last month')->format('Y-m-01 00:00:00');
        $thirtyDaysAgo  = $nowDt->modify('-30 days')->format('Y-m-d H:i:s');

        $billedThisMonth = $this->invoices->billedTotalBetween($thisMonthStart, $nextMonthStart);
        $billedLastMonth = $this->invoices->billedTotalBetween($lastMonthStart, $thisMonthStart);

        // Prior-year same month (YoY hero).
        $prevYearStart = $nowDt->modify('first day of this month')->modify('-1 year');
        $billedPrevYearMonth = $this->invoices->billedTotalBetween(
            $prevYearStart->format('Y-m-01 00:00:00'),
            $prevYearStart->modify('first day of next month')->format('Y-m-01 00:00:00'),
        );

        // Daily cumulative pace — current month (1..today) and the full prior month.
        $today = (int) $nowDt->format('j');
        $prevMonthLen = (int) $nowDt->modify('first day of last month')->format('t');
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
            monthlyBilled: $this->monthlyBilled($nowDt),
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
            $day = (int) substr($row['issued_at'], 8, 2);
            if ($day >= 1 && $day <= $daysCount) {
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
     * Issued-invoice totals for the last 6 calendar months (oldest→newest).
     *
     * @return list<array{month: string, billed_cents: int, count: int}>
     */
    private function monthlyBilled(DateTimeImmutable $nowDt): array
    {
        $firstOfThisMonth = $nowDt->modify('first day of this month')->setTime(0, 0, 0);
        $months = [];

        for ($k = 5; $k >= 0; --$k) {
            $start  = $firstOfThisMonth->modify("-{$k} month");
            $end    = $start->modify('+1 month');
            $bucket = $this->invoices->billedTotalBetween(
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
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
