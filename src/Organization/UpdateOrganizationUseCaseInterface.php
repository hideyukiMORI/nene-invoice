<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

interface UpdateOrganizationUseCaseInterface
{
    /** @throws OrganizationNotFoundException */
    public function execute(?int $actorUserId, int $id, UpdateOrganizationInput $input): Organization;
}
