<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

/**
 * Shared parsing for recurring-invoice write payloads: the required frequency
 * enum and the optional `is_active` flag. Run-date / line-item business rules
 * are enforced later in the use case.
 */
final class RecurringInvoiceRequest
{
    /**
     * @param array<string, mixed> $body
     *
     * @throws RecurringInvoiceValidationException
     */
    public static function requireFrequency(array $body): RecurringFrequency
    {
        $value = $body['frequency'] ?? null;
        $frequency = is_string($value) ? RecurringFrequency::tryFrom($value) : null;

        if ($frequency === null) {
            throw new RecurringInvoiceValidationException('frequency must be monthly or quarterly');
        }

        return $frequency;
    }

    /**
     * Reads the optional `is_active` flag, defaulting to true (a new or edited
     * schedule is active unless explicitly disabled).
     *
     * @param array<string, mixed> $body
     */
    public static function isActive(array $body): bool
    {
        $value = $body['is_active'] ?? true;

        return is_bool($value) ? $value : true;
    }
}
