<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\LineItem\LineItemRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/invoices` — creates a draft invoice directly in the caller's
 * organization (no originating quote).
 */
final readonly class CreateInvoiceHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateInvoiceUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            new CreateInvoiceInput(
                clientId: LineItemRequest::requireClientId($body),
                lines: LineItemRequest::parseLines($body),
                notes: LineItemRequest::optionalString($body, 'notes'),
            ),
        );

        return $this->json->create(InvoiceResponse::toArray($result->invoice, $result->lines), 201);
    }
}
