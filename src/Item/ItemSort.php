<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * Sort spec for the admin item list. `field` is restricted to a whitelist
 * (mapped to a column by the repository); anything else falls back to the
 * default description order. Direction defaults to ascending.
 */
final readonly class ItemSort
{
    public const FIELDS = ['description', 'unit_price', 'tax_rate'];

    public function __construct(
        public ?string $field = null,
        public bool $descending = false,
    ) {
    }

    public static function fromInput(?string $field, ?string $order): self
    {
        $resolved = $field !== null && in_array($field, self::FIELDS, true) ? $field : null;

        return new self($resolved, $order === 'desc');
    }
}
