<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/service-tokens` — lists the org's service-token registry records
 * (metadata only, never the token value). Requires ManageUsers.
 */
final readonly class ListServiceTokensHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListServiceTokensUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request, 50);

        $result = $this->useCase->execute($pagination->limit, $pagination->offset);

        return $this->json->create((new PaginationResponse(
            items: array_map(
                static fn (ServiceToken $t): array => ServiceTokenResponse::toArray($t),
                $result->items,
            ),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result->total,
        ))->toArray());
    }
}
