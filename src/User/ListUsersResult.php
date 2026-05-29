<?php

declare(strict_types=1);

namespace NeneInvoice\User;

final readonly class ListUsersResult
{
    /** @param list<User> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
