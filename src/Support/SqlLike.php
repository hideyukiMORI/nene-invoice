<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

/**
 * Helpers for building safe SQL LIKE predicates.
 */
final class SqlLike
{
    /**
     * Escapes LIKE wildcards so user input is matched literally. Use with
     * `... LIKE ? ESCAPE '!'` and wrap the result in `%…%` for a contains match.
     */
    public static function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
