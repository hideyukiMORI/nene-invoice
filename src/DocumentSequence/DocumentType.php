<?php

declare(strict_types=1);

namespace NeneInvoice\DocumentSequence;

/**
 * Numbered document types and their human-facing number prefixes.
 * Quote → `EST-2026-001`, Invoice → `INV-2026-001`.
 */
enum DocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';

    public function prefix(): string
    {
        return match ($this) {
            self::Quote => 'EST-',
            self::Invoice => 'INV-',
        };
    }
}
