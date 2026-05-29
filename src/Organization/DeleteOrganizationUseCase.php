<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

final readonly class DeleteOrganizationUseCase
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    /** @throws OrganizationNotFoundException */
    public function execute(int $id): void
    {
        $this->organizations->delete($id);
    }
}
