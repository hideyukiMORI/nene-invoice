<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizationsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $organizationsFactory,
        private Closure $auditFactory,
    ) {
    }

    /** @throws OrganizationSlugConflictException */
    public function execute(?int $actorUserId, CreateOrganizationInput $input): Organization
    {
        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $input): Organization {
            $organizations = ($this->organizationsFactory)($exec);

            $id = $organizations->save(new Organization(
                name: $input->name,
                slug: $input->slug,
                plan: $input->plan,
                isActive: true,
            ));

            $created = $organizations->findById($id);

            if ($created === null) {
                throw new LogicException('Organization disappeared immediately after creation.');
            }

            ($this->auditFactory)($exec)->record($actorUserId, $id, 'organization.created', 'organization', $id, null, OrganizationResponse::toArray($created));

            return $created;
        });
    }
}
