<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

/**
 * Mutable fields of a tenant for {@see UpdateOrganizationUseCase}. Every field is
 * optional (PATCH semantics): a null value means "leave unchanged". Presence is
 * decided at the HTTP boundary — `is_active: false` is a real value (suspend), so
 * the handler distinguishes an absent key from a false value before constructing
 * this.
 *
 * `slug` (URL identity) and `external_id` (federation link, ADR 0016) are
 * intentionally immutable here and are not part of this input.
 */
final readonly class UpdateOrganizationInput
{
    public function __construct(
        public ?string $name = null,
        public ?string $plan = null,
        public ?bool $isActive = null,
    ) {
    }
}
