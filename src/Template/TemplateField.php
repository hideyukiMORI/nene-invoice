<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\LineItem\LineItemInput;

/**
 * Parses + validates the template write payload shared by create and update.
 *
 * A template needs a name; line presets are optional (a notes-only template is
 * valid) but each present line must be well-formed with a supported tax rate.
 * The defaults seed document lines and never override the tax that applies.
 */
final class TemplateField
{
    /** Supported tax rates (basis points): 8% (reduced) and 10% (standard). */
    public const ALLOWED_TAX_RATES = [800, 1000];

    /**
     * @param array<string, mixed> $body
     *
     * @return array{name: string, notes: ?string, lines: list<LineItemInput>}
     * @throws ValidationException
     */
    public static function parse(array $body): array
    {
        $name = $body['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            throw new ValidationException([new ValidationError('body.name', 'Name is required.', 'required')]);
        }

        $rawLines = $body['line_items'] ?? [];
        if (!is_array($rawLines)) {
            throw new ValidationException([new ValidationError('body.line_items', 'line_items must be an array.', 'invalid')]);
        }

        $lines = [];
        foreach (array_values($rawLines) as $index => $raw) {
            $field = 'body.line_items.' . $index;

            if (!is_array($raw)) {
                throw new ValidationException([new ValidationError($field, 'Each line item must be an object.', 'invalid')]);
            }

            $description = $raw['description'] ?? null;
            $quantity = $raw['quantity'] ?? null;
            $unitPrice = $raw['unit_price_cents'] ?? null;
            $taxRate = $raw['tax_rate_bps'] ?? null;

            if (!is_string($description) || $description === '' || !is_int($quantity) || !is_int($unitPrice) || !is_int($taxRate)) {
                throw new ValidationException([new ValidationError($field, 'Each line item needs description (string) and quantity / unit_price_cents / tax_rate_bps (integers).', 'invalid')]);
            }

            if ($quantity <= 0 || $unitPrice < 0 || !in_array($taxRate, self::ALLOWED_TAX_RATES, true)) {
                throw new ValidationException([new ValidationError($field, 'Line item quantity must be > 0, unit price >= 0, and tax rate one of 800 or 1000.', 'invalid')]);
            }

            $lines[] = new LineItemInput($description, $quantity, $unitPrice, $taxRate);
        }

        $notes = $body['notes'] ?? null;

        return [
            'name' => trim($name),
            'notes' => is_string($notes) && $notes !== '' ? $notes : null,
            'lines' => $lines,
        ];
    }
}
