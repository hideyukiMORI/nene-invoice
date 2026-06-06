<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
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

    /** @throws OrganizationNotFoundException */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->organizations->findById($id);

        if ($existing === null) {
            throw new OrganizationNotFoundException($id);
        }

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $id, $existing): null {
            ($this->organizationsFactory)($exec)->delete($id);

            ($this->auditFactory)($exec)->record($actorUserId, $id, 'organization.deleted', 'organization', $id, OrganizationResponse::toArray($existing), null);

            return null;
        });
    }
}
