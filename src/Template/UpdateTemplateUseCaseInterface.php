<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

interface UpdateTemplateUseCaseInterface
{
    public function execute(?int $actorUserId, int $id, UpdateTemplateInput $input): TemplateWithLines;
}
