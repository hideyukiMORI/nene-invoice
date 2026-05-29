<?php

declare(strict_types=1);

namespace NeneInvoice\DocumentSequence;

interface DocumentSequenceRepositoryInterface
{
    /**
     * Atomically allocates and returns the next sequence number for the given
     * organization, document type, and year (starting at 1 each year).
     */
    public function nextNumber(int $organizationId, string $docType, int $year): int;
}
