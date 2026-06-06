<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

interface ListOrganizationsUseCaseInterface
{
    public function execute(int $limit, int $offset): ListOrganizationsResult;
}
