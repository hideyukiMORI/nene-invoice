<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

interface GetOrganizationByIdUseCaseInterface
{
    public function execute(int $id): Organization;
}
