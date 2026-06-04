<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nene2\Http\JsonResponseFactory;
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
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private ListAuditLogsUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();

        $limit = isset($query['limit']) && is_numeric($query['limit']) ? (int) $query['limit'] : self::DEFAULT_LIMIT;
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $offset = isset($query['offset']) && is_numeric($query['offset']) ? (int) $query['offset'] : 0;
        $offset = max(0, $offset);

        $filter = AuditLogFilterFactory::fromQueryParams($query);

        $result = $this->useCase->execute($filter, $limit, $offset);

        return $this->json->create([
            'items' => array_map(static fn (AuditLog $log): array => AuditLogResponse::toArray($log), $result->items),
            'total' => $result->total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
