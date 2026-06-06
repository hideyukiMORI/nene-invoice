<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

/**
 * Reads optional scalar fields from a decoded JSON request body, normalising
 * "absent / blank / wrong type" to null. Shared by the write handlers so the
 * trimming/null rules stay consistent.
 */
final class RequestField
{
    /**
     * Returns a non-empty string for the key, or null when missing/blank/non-string.
     *
     * @param array<string, mixed> $body
     */
    public static function optionalString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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
