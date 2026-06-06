<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

interface GetTemplateByIdUseCaseInterface
{
    public function execute(int $id): TemplateWithLines;
}
