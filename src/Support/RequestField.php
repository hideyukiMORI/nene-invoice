<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Reads optional scalar fields from a decoded JSON request body, normalising
 * "absent / blank / wrong type" to null. Shared by the write handlers so the
 * trimming/null rules stay consistent.
 */
final class RequestField
{
    /**
     * Returns a non-empty string for the key, or null when missing/blank/non-string.
     * Throws when the value exceeds `$maxLength` (Round 4 F1) so over-long input is
     * a graceful 422 rather than DB overflow / unbounded storage.
     *
     * @param array<string, mixed> $body
     *
     * @throws ValidationException
     */
    public static function optionalString(array $body, string $key, int $maxLength = TextLimit::NAME): ?string
    {
        $value = $body[$key] ?? null;

        if (!is_string($value) || $value === '') {
            return null;
        }

        TextLimit::check($value, 'body.' . $key, $maxLength);

        return $value;
    }

    /**
     * Returns a non-empty string for the key, throwing when missing/blank/non-string
     * (`required`) or when it exceeds `$maxLength` (`too_long`).
     *
     * @param array<string, mixed> $body
     *
     * @throws ValidationException
     */
    public static function requiredString(array $body, string $key, int $maxLength = TextLimit::NAME): string
    {
        $value = $body[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new ValidationException([new ValidationError('body.' . $key, sprintf('%s is required.', $key), 'required')]);
        }

        TextLimit::check($value, 'body.' . $key, $maxLength);

        return $value;
    }

    /**
     * Returns the integer value for the key, or null when missing/not an integer.
     *
     * @param array<string, mixed> $body
     */
    public static function optionalInt(array $body, string $key): ?int
    {
        $value = $body[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
