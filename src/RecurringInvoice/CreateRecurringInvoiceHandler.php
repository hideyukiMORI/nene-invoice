<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\LineItem\LineItemRequest;
use NeneInvoice\Support\RequestField;
use NeneInvoice\Support\TextLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/recurring-invoices` — creates a recurring-billing schedule in the
 * caller's organization (header + line template; totals computed by the use case).
 */
final readonly class CreateRecurringInvoiceHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateRecurringInvoiceUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $firstRunOn = $body['first_run_on'] ?? '';

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            new CreateRecurringInvoiceInput(
                clientId: LineItemRequest::requireClientId($body),
                name: RequestField::requiredString($body, 'name', TextLimit::NAME),
                frequency: RecurringInvoiceRequest::requireFrequency($body),
                firstRunOn: is_string($firstRunOn) ? $firstRunOn : '',
                lines: LineItemRequest::parseLines($body),
                isActive: RecurringInvoiceRequest::isActive($body),
                notes: RequestField::optionalString($body, 'notes', TextLimit::NOTE),
            ),
        );

        return $this->json->create(RecurringInvoiceResponse::toArray($result->schedule, $result->lines), 201);
    }
}
