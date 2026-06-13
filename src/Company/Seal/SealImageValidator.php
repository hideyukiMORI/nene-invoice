<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Validates an uploaded company seal (社印) image (Issue #448).
 *
 * The seal is transported as a base64 PNG in JSON (the API is JSON-only; no
 * multipart). Only transparent-capable PNG is accepted, within strict size and
 * dimension caps so the bytes stay small enough to store inline and embed in a
 * PDF as a data URI. Failures raise a 422 `validation-failed`, consistent with
 * every other field validation — no bespoke problem type.
 */
final class SealImageValidator
{
    /** Max decoded image size: a seal is small; this keeps DB rows and PDFs light. */
    public const MAX_BYTES = 512 * 1024;

    /** Max width/height in pixels. A seal needs no more than this at print scale. */
    public const MAX_DIMENSION = 1000;

    private const FIELD = 'body.image_base64';

    /**
     * Returns the normalized base64 string (no data-URI prefix) on success.
     *
     * @throws ValidationException when the value is missing or not an acceptable PNG
     */
    public static function validate(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            self::fail('Seal image is required.', 'required');
        }

        // Accept either a bare base64 string or a `data:image/png;base64,...` URI.
        $base64 = $value;
        if (preg_match('#^data:image/[a-z.+-]+;base64,#i', $base64) === 1) {
            $base64 = substr($base64, (int) strpos($base64, ',') + 1);
        }
        $base64 = trim($base64);

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            self::fail('Seal image must be valid base64-encoded data.', 'invalid_encoding');
        }

        if (strlen($binary) > self::MAX_BYTES) {
            self::fail(sprintf('Seal image must be %d KB or smaller.', intdiv(self::MAX_BYTES, 1024)), 'too_large');
        }

        $info = getimagesizefromstring($binary);
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            self::fail('Seal image must be a PNG.', 'invalid_format');
        }

        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            self::fail(sprintf('Seal image must be at most %1$d×%1$d pixels.', self::MAX_DIMENSION), 'too_large');
        }

        return $base64;
    }

    /**
     * @return never
     *
     * @throws ValidationException
     */
    private static function fail(string $message, string $code): void
    {
        throw new ValidationException([new ValidationError(self::FIELD, $message, $code)]);
    }
}
