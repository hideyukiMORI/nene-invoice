<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

final readonly class ListTemplatesResult
{
    /** @param list<Template> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
