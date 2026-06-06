<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

interface CreateOrganizationUseCaseInterface
{
    public function execute(?int $actorUserId, CreateOrganizationInput $input): Organization;
}
