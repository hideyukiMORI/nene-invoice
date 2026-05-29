<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices/{id}` — returns an invoice and its line items.
 */
final readonly class GetInvoiceByIdHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetInvoiceByIdUseCase $useCase,
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

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute($organizationId, $id);

        return $this->json->create(InvoiceResponse::toArray($result->invoice, $result->lines));
    }
}
