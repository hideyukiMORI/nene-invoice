<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Invoice\GetInvoiceByIdUseCase;
use NeneInvoice\Payment\ListPaymentsUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /api/invoices/{id}` — service read with authoritative `outstanding_cents`
 * and payment history (contract §2.2). Org-scoped to the service token; reuses
 * the operator use cases. Cross-tenant access surfaces as not-found.
 */
final readonly class GetServiceInvoiceHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetInvoiceByIdUseCase $getInvoice,
        private ListPaymentsUseCase $listPayments,
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

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        // Both repos are org-scoped via the holder (set by ServiceScopeMiddleware).
        $invoice = $this->getInvoice->execute($id);
        $payments = $this->listPayments->execute($id);

        return $this->json->create(
            ServiceInvoiceResponse::detail($invoice->invoice, $invoice->outstandingCents, $payments),
        );
    }
}
