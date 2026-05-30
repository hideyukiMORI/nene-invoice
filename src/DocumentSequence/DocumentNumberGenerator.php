<?php

declare(strict_types=1);

namespace NeneInvoice\DocumentSequence;

/**
 * Generates a formatted document number such as `EST-2026-001`, allocating the
 * next sequence per organization (resolved from the request-scoped org holder),
 * document type, and year.
 */
final readonly class DocumentNumberGenerator
{
    public function __construct(
        private DocumentSequenceRepositoryInterface $sequences,
    ) {
    }

    public function next(DocumentType $type, int $year): string
    {
        $sequence = $this->sequences->nextNumber($type->value, $year);

        return sprintf('%s%d-%03d', $type->prefix(), $year, $sequence);
    }
}
