<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

interface DeleteTemplateUseCaseInterface
{
    public function execute(?int $actorUserId, int $id): void;
}
