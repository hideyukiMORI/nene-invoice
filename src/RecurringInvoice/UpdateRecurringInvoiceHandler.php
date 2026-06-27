<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\LineItem\LineItemRequest;
use NeneInvoice\Support\RequestField;
use NeneInvoice\Support\TextLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/recurring-invoices/{id}` — edits a recurring schedule in the
 * caller's organization (replaces the line template, recomputes totals).
 */
final readonly class UpdateRecurringInvoiceHandler implements RequestHandlerInterface
{
    public function __construct(
        private UpdateRecurringInvoiceUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $body = JsonRequestBodyParser::parse($request);

        $nextRunOn = $body['next_run_on'] ?? '';

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            $id,
            new UpdateRecurringInvoiceInput(
                clientId: LineItemRequest::requireClientId($body),
                name: RequestField::requiredString($body, 'name', TextLimit::NAME),
                frequency: RecurringInvoiceRequest::requireFrequency($body),
                nextRunOn: is_string($nextRunOn) ? $nextRunOn : '',
                lines: LineItemRequest::parseLines($body),
                isActive: RecurringInvoiceRequest::isActive($body),
                notes: RequestField::optionalString($body, 'notes', TextLimit::NOTE),
            ),
        );

        return $this->json->create(RecurringInvoiceResponse::toArray($result->schedule, $result->lines));
    }
}
