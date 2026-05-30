<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use DateTimeImmutable;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;

/**
 * Assembles the dashboard summary: unpaid/overdue counts, outstanding balance,
 * and the most recent 5 unpaid invoices — all in two queries.
 */
final readonly class GetDashboardSummaryUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
    ) {
    }

    public function execute(int $organizationId): DashboardSummary
    {
        $now  = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = $this->invoices->getDashboardData($organizationId, $now);

        $outstandingTotalCents = $this->payments->outstandingTotalForOrganization($organizationId);

        return new DashboardSummary(
            unpaidCount: $data['unpaid_count'],
            overdueCount: $data['overdue_count'],
            outstandingTotalCents: $outstandingTotalCents,
            recentUnpaid: $data['recent_unpaid'],
        );
    }
}
