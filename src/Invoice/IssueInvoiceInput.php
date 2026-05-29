<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

final readonly class IssueInvoiceInput
{
    public function __construct(
        public bool $qualified = true,
        public ?string $dueAt = null,
    ) {
    }
}
