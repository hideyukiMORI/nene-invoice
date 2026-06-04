<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * Sort spec for the admin quote list. `field` is restricted to a whitelist
 * (mapped to a column by the repository); anything else falls back to the
 * default newest-first order. Direction defaults to descending.
 */
final readonly class QuoteSort
{
    public const FIELDS = ['number', 'client', 'status', 'issued_at', 'valid_until', 'total'];

    public function __construct(
        public ?string $field = null,
        public bool $descending = true,
    ) {
    }

    public static function fromInput(?string $field, ?string $order): self
    {
        $resolved = $field !== null && in_array($field, self::FIELDS, true) ? $field : null;

        return new self($resolved, $order !== 'asc');
    }
}
