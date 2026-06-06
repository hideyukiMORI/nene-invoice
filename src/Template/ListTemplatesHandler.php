<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/templates` — lists template headers (without lines) in the
 * resolved organization. Requires ViewBilling.
 */
final readonly class ListTemplatesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListTemplatesUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request, 50);

        $result = $this->useCase->execute($pagination->limit, $pagination->offset);

        return $this->json->create((new PaginationResponse(
            items: array_map(
                static fn (Template $t): array => TemplateResponse::toArray($t, []),
                $result->items,
            ),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result->total,
        ))->toArray());
    }
}
