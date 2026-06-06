<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * Parses + validates the item write payload shared by create and update.
 *
 * Tax rate is restricted to the supported Japanese consumption-tax rates; the
 * default unit price is integer cents and must be non-negative. These defaults
 * seed document lines but never override the tax that applies to a sale.
 */
final class ItemField
{
    /** Supported tax rates (basis points): 8% (reduced) and 10% (standard). */
    public const ALLOWED_TAX_RATES = [800, 1000];

    /**
     * @param array<string, mixed> $body
     *
     * @return array{error: ?string, input: ?ItemWriteValues}
     */
    public static function parse(array $body): array
    {
        $description = $body['description'] ?? null;
        if (!is_string($description) || trim($description) === '') {
            return ['error' => '"description" is required.', 'input' => null];
        }

        $price = $body['default_unit_price_cents'] ?? null;
        if (!is_int($price) || $price < 0) {
            return ['error' => '"default_unit_price_cents" must be a non-negative integer.', 'input' => null];
        }

        $tax = $body['default_tax_rate_bps'] ?? null;
        if (!is_int($tax) || !in_array($tax, self::ALLOWED_TAX_RATES, true)) {
            return ['error' => '"default_tax_rate_bps" must be one of 800 or 1000.', 'input' => null];
        }

        return ['error' => null, 'input' => new ItemWriteValues(trim($description), $price, $tax)];
    }
}
