<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use NeneInvoice\LineItem\LineItemInput;

final readonly class CreateTemplateInput
{
    /** @param list<LineItemInput> $lines */
    public function __construct(
        public string $name,
        public array $lines,
        public ?string $notes = null,
    ) {
    }
}
