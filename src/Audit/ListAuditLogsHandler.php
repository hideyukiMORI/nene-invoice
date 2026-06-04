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

        $filter = new AuditLogFilter(
            entityType: self::stringParam($query, 'entity_type'),
            action: self::stringParam($query, 'action'),
            actorUserId: isset($query['actor_user_id']) && is_numeric($query['actor_user_id'])
                ? (int) $query['actor_user_id']
                : null,
            createdFrom: self::dateParam($query, 'created_from', false),
            createdTo: self::dateParam($query, 'created_to', true),
        );

        $result = $this->useCase->execute($filter, $limit, $offset);

        return $this->json->create([
            'items' => array_map(static fn (AuditLog $log): array => AuditLogResponse::toArray($log), $result->items),
            'total' => $result->total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function stringParam(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalizes a `YYYY-MM-DD` (or full datetime) bound into an inclusive
     * `Y-m-d H:i:s` comparison value. A date-only `endOfDay` bound expands to
     * `23:59:59` so the whole day is included. Invalid input is ignored.
     *
     * @param array<string, mixed> $query
     */
    private static function dateParam(array $query, string $key, bool $endOfDay): ?string
    {
        $value = self::stringParam($query, $key);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
