<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;

/**
 * Revokes a service token by id (org-scoped). Sets `revoked_at` so the
 * request-time {@see ServiceTokenAuthorizerInterface} rejects it immediately.
 * The revoke write and its audit record commit atomically (ADR 0008).
 */
final readonly class RevokeServiceTokenUseCase implements RevokeServiceTokenUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ServiceTokenRepositoryInterface $tokensFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $tokensFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(?int $actorUserId, int $id): void
    {
        $organizationId = $this->orgId->get();
        $revokedAt      = $this->clock->now()->format('Y-m-d H:i:s');

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($id, $revokedAt, $actorUserId, $organizationId): null {
            // Throws ServiceTokenNotFoundException for an unknown / foreign id.
            ($this->tokensFactory)($exec)->revoke($id, $revokedAt);

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'service_token.revoked',
                entityType: 'service_token',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: null,
                after: ['revoked_at' => $revokedAt],
            ));

            return null;
        });
    }
}
