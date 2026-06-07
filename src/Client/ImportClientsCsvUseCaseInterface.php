<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

interface ImportClientsCsvUseCaseInterface
{
    public function execute(?int $actorUserId, string $raw, bool $dryRun): ClientImportResult;
}
