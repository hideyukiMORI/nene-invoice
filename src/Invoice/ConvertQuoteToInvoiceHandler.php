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
 * `POST /admin/quotes/{id}/convert` — creates a draft invoice from an accepted quote.
 */
final readonly class ConvertQuoteToInvoiceHandler implements RequestHandlerInterface
{
    public function __construct(
        private ConvertQuoteToInvoiceUseCase $useCase,
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
        $quoteId = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute($organizationId, AuthContext::userId($request), $quoteId);

        return $this->json->create(InvoiceResponse::toArray($result->invoice, $result->lines), 201);
    }
}
