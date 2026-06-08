<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Maximum lengths for user-supplied text fields, matching the DB column limits
 * (Round 4 finding F1). Without these, an over-long value is stored unbounded on
 * SQLite (bloat / oversized PDFs) and raises an unhandled overflow error → HTTP
 * 500 on MySQL / PostgreSQL (VARCHAR strict mode). Validating here returns a
 * graceful 422 instead, consistently across engines.
 *
 * Lengths are measured in Unicode code points (`mb_strlen`).
 */
final class TextLimit
{
    /** VARCHAR(255): names, emails, contact names, bank names, external references, idempotency keys. */
    public const NAME = 255;

    /** VARCHAR(100): organization slug. */
    public const SLUG = 100;

    /** VARCHAR(32): phone, account type, plan, payment method. */
    public const TINY = 32;

    /** VARCHAR(64): bank account number. */
    public const ACCOUNT = 64;

    /** VARCHAR(1024): logo URL, line-item description. */
    public const LONG = 1024;

    /** TEXT free-form fields (notes, addresses): a sane cap well under TEXT's 64 KB. */
    public const NOTE = 5000;

    /**
     * Throws a {@see ValidationException} when a non-null string exceeds the limit.
     *
     * @throws ValidationException
     */
    public static function check(?string $value, string $field, int $max): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new ValidationException([new ValidationError(
                $field,
                sprintf('Must be at most %d characters.', $max),
                'too_long',
            )]);
        }
    }
}
