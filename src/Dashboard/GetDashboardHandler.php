<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/dashboard` — returns the admin dashboard summary for the resolved
 * organization (scoped by the repository via the org holder).
 * Capability: ViewBilling (listed explicitly in CapabilityResolver).
 */
final readonly class GetDashboardHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetDashboardSummaryUseCase $useCase,
        private PaymentRepositoryInterface $payments,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $summary = $this->useCase->execute();

        $invoiceIds  = array_filter(
            array_map(static fn (Invoice $i): ?int => $i->id, $summary->recentUnpaid),
        );
        $paidByInvoice = $this->payments->sumPaidForInvoices(array_values($invoiceIds));

        $recentUnpaid = array_map(
            static function (Invoice $i) use ($paidByInvoice): array {
                $outstanding = $i->id !== null && isset($paidByInvoice[$i->id])
                    ? max(0, $i->totalCents - $paidByInvoice[$i->id])
                    : $i->totalCents;

                return InvoiceResponse::toArray($i, null, $outstanding);
            },
            $summary->recentUnpaid,
        );

        return $this->json->create([
            'unpaid_count'            => $summary->unpaidCount,
            'overdue_count'           => $summary->overdueCount,
            'outstanding_total_cents' => $summary->outstandingTotalCents,
            'recent_unpaid'           => $recentUnpaid,
            'received_this_month_cents' => $summary->receivedThisMonthCents,
            'received_last_month_cents' => $summary->receivedLastMonthCents,
            'aging'                     => $summary->aging,
        ]);
    }
}
