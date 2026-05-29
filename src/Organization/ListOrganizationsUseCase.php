<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

final readonly class ListOrganizationsUseCase
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(int $limit, int $offset): ListOrganizationsResult
    {
        return new ListOrganizationsResult(
            $this->organizations->findAll($limit, $offset),
            $this->organizations->count(),
        );
    }
}
