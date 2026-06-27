<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/recurring-invoices` — lists recurring-billing schedule headers in
 * the resolved organization (scoped by the repository via the org holder). The
 * line template is not included in the list (client_name null).
 */
final readonly class ListRecurringInvoicesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListRecurringInvoicesUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request);

        $result = $this->useCase->execute($pagination->limit, $pagination->offset);

        return $this->json->create((new PaginationResponse(
            items: array_map(
                static fn (RecurringInvoice $schedule): array => RecurringInvoiceResponse::toArray($schedule),
                $result->items,
            ),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result->total,
        ))->toArray());
    }
}
