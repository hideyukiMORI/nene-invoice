<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

interface CreateTemplateUseCaseInterface
{
    public function execute(?int $actorUserId, CreateTemplateInput $input): TemplateWithLines;
}
