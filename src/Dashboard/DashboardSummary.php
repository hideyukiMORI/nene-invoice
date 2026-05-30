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
    /** @param list<Invoice> $recentUnpaid */
    public function __construct(
        public int $unpaidCount,
        public int $overdueCount,
        public int $outstandingTotalCents,
        public array $recentUnpaid,
    ) {
    }
}
