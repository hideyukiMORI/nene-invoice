<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

interface ExportClientsCsvUseCaseInterface
{
    public function execute(ClientListFilter $filter): string;
}
