<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

/**
 * Page of service-token registry records plus the total count for pagination.
 */
final readonly class ListServiceTokensResult
{
    /**
     * @param list<ServiceToken> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
