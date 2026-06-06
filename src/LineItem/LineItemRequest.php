<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Shared parsing/validation for document write payloads (quote & invoice create):
 * the required client id, the non-empty line-item array, and optional strings.
 * Throws {@see ValidationException} (→ 422 with `errors[]`) on malformed input;
 * tax-rate / amount business rules are enforced later in the use case.
 */
final class LineItemRequest
{
    /**
     * @param array<string, mixed> $body
     *
     * @throws ValidationException
     */
    public static function requireClientId(array $body): int
    {
        $clientId = $body['client_id'] ?? null;

        if (is_int($clientId)) {
            return $clientId;
        }

        if (is_string($clientId) && ctype_digit($clientId)) {
            return (int) $clientId;
        }

        throw new ValidationException([new ValidationError('body.client_id', 'A client_id is required.', 'required')]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<LineItemInput>
     * @throws ValidationException
     */
    public static function parseLines(array $body): array
    {
        $rawLines = $body['line_items'] ?? null;

        if (!is_array($rawLines) || $rawLines === []) {
            throw new ValidationException([new ValidationError('body.line_items', 'line_items must be a non-empty array.', 'required')]);
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

            $lines[] = new LineItemInput($description, $quantity, $unitPrice, $taxRate);
        }

        return $lines;
    }
}
