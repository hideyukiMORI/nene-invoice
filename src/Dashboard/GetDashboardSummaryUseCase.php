<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use DateTimeImmutable;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;

/**
 * Assembles the dashboard summary: unpaid/overdue counts, outstanding balance,
 * and the most recent 5 unpaid invoices — all in two queries.
 */
final readonly class GetDashboardSummaryUseCase
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(): DashboardSummary
    {
        $now  = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = $this->invoices->getDashboardData($now);

        // Payment repo is not yet org-scoped; pass the resolved org explicitly.
        $outstandingTotalCents = $this->payments->outstandingTotalForOrganization($this->orgId->get());

        return new DashboardSummary(
            unpaidCount: $data['unpaid_count'],
            overdueCount: $data['overdue_count'],
            outstandingTotalCents: $outstandingTotalCents,
            recentUnpaid: $data['recent_unpaid'],
        );
    }
}
