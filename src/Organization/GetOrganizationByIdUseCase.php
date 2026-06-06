<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

final readonly class GetOrganizationByIdUseCase implements GetOrganizationByIdUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    /** @throws OrganizationNotFoundException */
    public function execute(int $id): Organization
    {
        $organization = $this->organizations->findById($id);

        if ($organization === null) {
            throw new OrganizationNotFoundException($id);
        }

        return $organization;
    }
}
