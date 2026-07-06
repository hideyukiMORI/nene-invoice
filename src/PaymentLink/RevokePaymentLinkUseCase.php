<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;

/**
 * Revokes an active payment link so it can no longer be paid (ADR 0012 §3).
 * Org-scoped and idempotent: a link belonging to another organization is
 * invisible (NotFound), and revoking an already-terminal link is a no-op.
 */
final readonly class RevokePaymentLinkUseCase implements RevokePaymentLinkUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): PaymentLinkRepositoryInterface $linksFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $linksFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(?int $actorUserId, int $paymentLinkId): RevokeOutcome
    {
        $organizationId = $this->orgId->get();
        $now            = $this->clock->now()->format('Y-m-d H:i:s');

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $paymentLinkId, $now): RevokeOutcome {
            $links = ($this->linksFactory)($exec);

            $link = $links->findById($paymentLinkId);
            if ($link === null) {
                return RevokeOutcome::NotFound;
            }

            if (!$links->markRevoked($paymentLinkId, $now)) {
                // Exists but was not active (already revoked/paid) — idempotent no-op.
                return RevokeOutcome::AlreadyInactive;
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'payment_link.revoked',
                entityType: 'payment_link',
                entityId: $paymentLinkId,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: ['status' => PaymentLinkStatus::Active->value],
                after: ['status' => PaymentLinkStatus::Revoked->value],
            ));

            return RevokeOutcome::Revoked;
        });
    }
}
