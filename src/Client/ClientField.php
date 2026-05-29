<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

/**
 * Small helpers for parsing optional client fields from a decoded JSON body.
 */
final class ClientField
{
    /**
     * Returns a trimmed non-empty string for the key, or null when the value is
     * missing, not a string, or empty.
     *
     * @param array<string, mixed> $body
     */
    public static function optionalString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
