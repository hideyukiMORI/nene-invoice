<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

/**
 * Admin read filter for the client list. `search` matches name, contact name,
 * email, or registration number (substring). Empty = list everything.
 */
final readonly class ClientListFilter
{
    public function __construct(
        public ?string $search = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->search === null;
    }
}
