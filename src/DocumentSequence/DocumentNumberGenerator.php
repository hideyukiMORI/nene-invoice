<?php

declare(strict_types=1);

namespace NeneInvoice\DocumentSequence;

/**
 * Generates a formatted document number such as `EST-2026-001`, allocating the
 * next sequence per organization, document type, and year.
 */
final readonly class DocumentNumberGenerator
{
    public function __construct(
        private DocumentSequenceRepositoryInterface $sequences,
    ) {
    }

    public function next(int $organizationId, DocumentType $type, int $year): string
    {
        $sequence = $this->sequences->nextNumber($organizationId, $type->value, $year);

        return sprintf('%s%d-%03d', $type->prefix(), $year, $sequence);
    }
}
