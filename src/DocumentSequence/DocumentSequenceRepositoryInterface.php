<?php

declare(strict_types=1);

namespace NeneInvoice\DocumentSequence;

interface DocumentSequenceRepositoryInterface
{
    /**
     * Atomically allocates and returns the next sequence number for the given
     * document type and year (starting at 1 each year). The organization is read
     * from the request-scoped org holder (ADR 0006).
     */
    public function nextNumber(string $docType, int $year): int;
}
