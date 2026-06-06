<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\LineItem\LineItemRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/quotes` — creates a draft quote in the caller's organization.
 */
final readonly class CreateQuoteHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateQuoteUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            new CreateQuoteInput(
                clientId: LineItemRequest::requireClientId($body),
                lines: LineItemRequest::parseLines($body),
                validUntil: LineItemRequest::optionalString($body, 'valid_until'),
                notes: LineItemRequest::optionalString($body, 'notes'),
            ),
        );

        return $this->json->create(QuoteResponse::toArray($result->quote, $result->lines), 201);
    }
}
