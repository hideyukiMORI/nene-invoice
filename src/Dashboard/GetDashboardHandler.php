<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/dashboard` — returns the admin dashboard summary.
 * Capability: ViewBilling (GET on /admin/invoices path prefix in CapabilityResolver).
 *
 * Note: the path `/admin/dashboard` is NOT under `/admin/invoices` so we need
 * to ensure CapabilityResolver handles it. We gate it at ViewBilling by listing
 * it explicitly in CapabilityResolver.
 */
final readonly class GetDashboardHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetDashboardSummaryUseCase $useCase,
        private PaymentRepositoryInterface $payments,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'organization-not-resolved', 'Organization Required', 400, 'This action requires an organization context.');
        }

        $summary = $this->useCase->execute($organizationId);

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
        ]);
    }
}
