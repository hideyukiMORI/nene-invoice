<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices/{id}` — returns an invoice and its line items in the
 * resolved organization (scoped by the repository via the org holder).
 */
final readonly class GetInvoiceByIdHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetInvoiceByIdUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute($id);

        return $this->json->create(
            InvoiceResponse::toArray($result->invoice, $result->lines, $result->outstandingCents),
        );
    }
}
