<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizationsFactory
     */
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $organizationsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
    ) {
    }

    /** @throws OrganizationNotFoundException */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->organizations->findById($id);

        if ($existing === null) {
            throw new OrganizationNotFoundException($id);
        }

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $id, $existing): null {
            ($this->organizationsFactory)($exec)->delete($id);

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'organization.deleted',
                entityType: 'organization',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $id,
                before: OrganizationResponse::toArray($existing),
                after: null,
            ));

            return null;
        });
    }
}
