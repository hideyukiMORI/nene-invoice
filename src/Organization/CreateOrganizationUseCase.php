<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use LogicException;

final readonly class CreateOrganizationUseCase
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    /** @throws OrganizationSlugConflictException */
    public function execute(CreateOrganizationInput $input): Organization
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

        return $created;
    }
}
