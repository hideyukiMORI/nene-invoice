<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

/**
 * A named quote/invoice template (#329), scoped to one organization. Holds the
 * header (name + notes); the line presets live in `line_items` with
 * parent_type = 'template'. Soft-deleted so references stay coherent.
 */
final readonly class Template
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public ?string $notes = null,
        public bool $isDeleted = false,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
