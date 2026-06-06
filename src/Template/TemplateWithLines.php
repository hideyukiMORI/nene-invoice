<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use NeneInvoice\LineItem\LineItem;

/**
 * A template together with its line presets (read model for detail responses).
 */
final readonly class TemplateWithLines
{
    /** @param list<LineItem> $lines */
    public function __construct(
        public Template $template,
        public array $lines,
    ) {
    }
}
