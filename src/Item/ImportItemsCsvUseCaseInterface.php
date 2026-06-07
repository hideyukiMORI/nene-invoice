<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use NeneInvoice\Support\CsvImportResult;

interface ImportItemsCsvUseCaseInterface
{
    public function execute(?int $actorUserId, string $raw, bool $dryRun): CsvImportResult;
}
