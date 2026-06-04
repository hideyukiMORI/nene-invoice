<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use NeneInvoice\Invoice\Invoice;

/**
 * Aggregated read model for the admin dashboard. All counts are for the caller's
 * organization. Money is integer cents (ADR 0004).
 */
final readonly class DashboardSummary
{
    /**
     * @param list<Invoice>                                                $recentUnpaid
     * @param array{current: int, overdue_1_30: int, overdue_31_plus: int} $aging outstanding receivable bucketed by overdue age, in cents
     * @param list<array{month: string, billed_cents: int, count: int}>    $monthlyBilled issued-invoice totals per month (oldest→newest, Issue #272)
     * @param list<array{day: int, cumulative_cents: int}>                 $billedDailyCurrent cumulative issued total per day, current month 1..today (Issue #281)
     * @param list<array{day: int, cumulative_cents: int}>                 $billedDailyPrevMonth cumulative issued total per day, full previous month
     */
    public function __construct(
        public int $unpaidCount,
        public int $overdueCount,
        public int $outstandingTotalCents,
        public array $recentUnpaid,
        public int $receivedThisMonthCents,
        public int $receivedLastMonthCents,
        public array $aging,
        public int $billedThisMonthCents,
        public int $billedLastMonthCents,
        public array $monthlyBilled,
        public int $billedPrevYearMonthCents,
        public array $billedDailyCurrent,
        public array $billedDailyPrevMonth,
    ) {
    }
}
