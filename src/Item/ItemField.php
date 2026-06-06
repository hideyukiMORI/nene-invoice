<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

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
     * @throws ValidationException
     */
    public static function parse(array $body): ItemWriteValues
    {
        $errors = [];

        $description = $body['description'] ?? null;
        if (!is_string($description) || trim($description) === '') {
            $errors[] = new ValidationError('body.description', 'Description is required.', 'required');
            $description = '';
        }

        $price = $body['default_unit_price_cents'] ?? null;
        if (!is_int($price) || $price < 0) {
            $errors[] = new ValidationError('body.default_unit_price_cents', 'Default unit price must be a non-negative integer.', 'invalid');
            $price = 0;
        }

        $tax = $body['default_tax_rate_bps'] ?? null;
        if (!is_int($tax) || !in_array($tax, self::ALLOWED_TAX_RATES, true)) {
            $errors[] = new ValidationError('body.default_tax_rate_bps', 'Default tax rate must be one of 800 or 1000.', 'invalid');
            $tax = 1000;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new ItemWriteValues(trim($description), $price, $tax);
    }
}
