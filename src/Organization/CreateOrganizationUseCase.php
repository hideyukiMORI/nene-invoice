<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use LogicException;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private AuditRecorderInterface $audit,
    ) {
    }

    /** @throws OrganizationSlugConflictException */
    public function execute(?int $actorUserId, CreateOrganizationInput $input): Organization
    {
        $id = $this->organizations->save(new Organization(
            name: $input->name,
            slug: $input->slug,
            plan: $input->plan,
            isActive: true,
        ));

        $created = $this->organizations->findById($id);

        if ($created === null) {
            throw new LogicException('Organization disappeared immediately after creation.');
        }

        $this->audit->record($actorUserId, $id, 'organization.created', 'organization', $id, null, OrganizationResponse::toArray($created));

        return $created;
    }
}
