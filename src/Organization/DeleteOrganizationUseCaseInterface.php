<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

interface DeleteOrganizationUseCaseInterface
{
    public function execute(?int $actorUserId, int $id): void;
}
