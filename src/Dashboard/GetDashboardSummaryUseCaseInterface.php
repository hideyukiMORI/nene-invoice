<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

interface GetDashboardSummaryUseCaseInterface
{
    public function execute(): DashboardSummary;
}
