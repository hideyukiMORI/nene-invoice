<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

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
        private ConvertQuoteToInvoiceUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $quoteId = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute(AuthContext::userId($request), $quoteId);

        return $this->json->create(InvoiceResponse::toArray($result->invoice, $result->lines), 201);
    }
}
