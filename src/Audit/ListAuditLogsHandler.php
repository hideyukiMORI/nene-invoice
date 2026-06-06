<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/audit-logs` — lists the audit trail for the caller's organization
 * (admin oversight; gated by `manage_users` in CapabilityResolver). The
 * organization is resolved upstream (OrgResolverMiddleware) into the
 * request-scoped holder.
 */
final readonly class ListAuditLogsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListAuditLogsUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request);
        $query = $request->getQueryParams();

        $filter = AuditLogFilterFactory::fromQueryParams($query);

        $result = $this->useCase->execute($filter, $pagination->limit, $pagination->offset);

        return $this->json->create((new PaginationResponse(
            items: array_map(static fn (AuditLog $log): array => AuditLogResponse::toArray($log), $result->items),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result->total,
        ))->toArray());
    }
}
