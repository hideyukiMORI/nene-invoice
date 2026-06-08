<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Parses `amount_cents` strictly as integer cents (Round 4 finding F2).
 *
 * Money is integer cents — floats are forbidden (CLAUDE.md / ADR 0004). The
 * previous `(int) $value` cast silently truncated `100.5 → 100`; here a float or
 * non-integer value is rejected with a 422 instead. A pure-integer string
 * (e.g. "1500") is accepted for client convenience, mirroring client_id parsing.
 */
final class PaymentAmount
{
    /**
     * @param array<string, mixed> $body
     *
     * @throws ValidationException
     */
    public static function fromBody(array $body): int
    {
        $value = $body['amount_cents'] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new ValidationException([new ValidationError('body.amount_cents', 'amount_cents must be an integer (cents).', 'invalid')]);
    }
}
