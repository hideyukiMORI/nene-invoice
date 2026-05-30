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
        $now  = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = $this->invoices->getDashboardData($now);

        $outstandingTotalCents = $this->payments->outstandingTotal();

        return new DashboardSummary(
            unpaidCount: $data['unpaid_count'],
            overdueCount: $data['overdue_count'],
            outstandingTotalCents: $outstandingTotalCents,
            recentUnpaid: $data['recent_unpaid'],
        );
    }
}
