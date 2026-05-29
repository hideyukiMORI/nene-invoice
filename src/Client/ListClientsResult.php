<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

final readonly class ListClientsResult
{
    /** @param list<Client> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
