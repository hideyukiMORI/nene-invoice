<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

final readonly class ListOrganizationsResult
{
    /** @param list<Organization> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
