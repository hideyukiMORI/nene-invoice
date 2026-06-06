<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

interface ListTemplatesUseCaseInterface
{
    public function execute(int $limit, int $offset): ListTemplatesResult;
}
