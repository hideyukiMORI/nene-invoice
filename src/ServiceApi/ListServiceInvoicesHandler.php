<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceListFilter;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\ListInvoicesUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /api/invoices` — service read for NeNe Clear. Org-scoped to the service
 * token. Reuses the operator list use case (which computes outstanding) and
 * serializes the contract read model. Filters (status/overdue/outstanding_gt/…)
 * are an additive follow-up.
 */
final readonly class ListServiceInvoicesHandler implements RequestHandlerInterface
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private ListInvoicesUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = ServiceAuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'insufficient-scope', 'Forbidden', 403, 'The service token is not scoped to an organization.');
        }

        $query = $request->getQueryParams();
        $limit = isset($query['limit']) && is_numeric($query['limit']) ? (int) $query['limit'] : self::DEFAULT_LIMIT;
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $offset = isset($query['offset']) && is_numeric($query['offset']) ? (int) $query['offset'] : 0;
        $offset = max(0, $offset);

        $result = $this->useCase->execute($limit, $offset, $this->filterFrom($query));
        $outstanding = $result->outstandingByInvoiceId;

        return $this->json->create([
            'items' => array_map(
                static fn (Invoice $i): array => ServiceInvoiceResponse::listItem(
                    $i,
                    $i->id !== null ? ($outstanding[$i->id] ?? $i->totalCents) : $i->totalCents,
                ),
                $result->items,
            ),
            'total' => $result->total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Builds the read filter from query params (contract §2.1).
     *
     * @param array<string, mixed> $query
     */
    private function filterFrom(array $query): InvoiceListFilter
    {
        $statuses = [];
        if (isset($query['status']) && is_string($query['status'])) {
            $allowed = array_map(static fn (InvoiceStatus $s): string => $s->value, InvoiceStatus::cases());
            foreach (explode(',', $query['status']) as $candidate) {
                $candidate = trim($candidate);
                if (in_array($candidate, $allowed, true)) {
                    $statuses[] = $candidate;
                }
            }
        }

        $clientId = isset($query['client_id']) && is_numeric($query['client_id'])
            ? (int) $query['client_id']
            : null;

        $dueBefore = isset($query['due_before']) && is_string($query['due_before']) && $query['due_before'] !== ''
            ? $query['due_before']
            : null;
        $dueAfter = isset($query['due_after']) && is_string($query['due_after']) && $query['due_after'] !== ''
            ? $query['due_after']
            : null;

        $overdue = isset($query['overdue']) && $query['overdue'] === 'true';
        // Contract example is outstanding_gt=0 ("open receivables"); any provided
        // numeric value selects invoices with a positive outstanding balance.
        $outstandingOnly = isset($query['outstanding_gt']) && is_numeric($query['outstanding_gt']);

        return new InvoiceListFilter(
            statuses: $statuses,
            clientId: $clientId,
            dueBefore: $dueBefore,
            dueAfter: $dueAfter,
            overdueOnly: $overdue,
            outstandingOnly: $outstandingOnly,
        );
    }
}
