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

        return new DashboardSummary(
            unpaidCount: $data['unpaid_count'],
            overdueCount: $data['overdue_count'],
            outstandingTotalCents: $outstandingTotalCents,
            recentUnpaid: $data['recent_unpaid'],
            receivedThisMonthCents: $this->payments->receivedTotalBetween($thisMonthStart, $nextMonthStart),
            receivedLastMonthCents: $this->payments->receivedTotalBetween($lastMonthStart, $thisMonthStart),
            aging: $this->payments->agingBuckets($now, $thirtyDaysAgo),
        );
    }
}
