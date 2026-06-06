<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/organizations` — superadmin lists tenants (paginated).
 */
final readonly class ListOrganizationsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListOrganizationsUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request);

        $result = $this->useCase->execute($pagination->limit, $pagination->offset);

        return $this->json->create((new PaginationResponse(
            items: array_map(static fn (Organization $o): array => OrganizationResponse::toArray($o), $result->items),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result->total,
        ))->toArray());
    }
}
