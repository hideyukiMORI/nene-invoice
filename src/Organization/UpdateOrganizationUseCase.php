<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneInvoice\Audit\AuditRecorderInterface;

/**
 * Updates a tenant's mutable fields — including `is_active`, which suspends
 * (`false`) or reactivates (`true`) the organization. Suspending it makes
 * OrgResolverMiddleware return 403 for that tenant's requests (ADR 0006), so the
 * host can lock a managed tenant out without deleting its data.
 *
 * `slug` and `external_id` are preserved from the existing record — they are not
 * mutable through this path. The change is recorded as `organization.updated`
 * with before/after snapshots in the same transaction as the write.
 */
final readonly class UpdateOrganizationUseCase implements UpdateOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizationsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     */
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $organizationsFactory,
        private Closure $auditFactory,
    ) {
    }

    public function execute(?int $actorUserId, int $id, UpdateOrganizationInput $input): Organization
    {
        $existing = $this->organizations->findById($id);

        if ($existing === null) {
            throw new OrganizationNotFoundException($id);
        }

        $updated = new Organization(
            name: $input->name ?? $existing->name,
            slug: $existing->slug,
            plan: $input->plan ?? $existing->plan,
            isActive: $input->isActive ?? $existing->isActive,
            id: $existing->id,
            externalId: $existing->externalId,
            customDomain: $existing->customDomain,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        );

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $id, $existing, $updated): Organization {
            $organizations = ($this->organizationsFactory)($exec);
            $organizations->update($updated);

            $fresh = $organizations->findById($id);

            if ($fresh === null) {
                throw new LogicException('Organization disappeared immediately after update.');
            }

            ($this->auditFactory)($exec)->record(
                $actorUserId,
                $id,
                'organization.updated',
                'organization',
                $id,
                OrganizationResponse::toArray($existing),
                OrganizationResponse::toArray($fresh),
            );

            return $fresh;
        });
    }
}
