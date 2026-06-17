<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * Generation and hashing of opaque session secrets (ADR 0014).
 *
 * The refresh token and CSRF token are high-entropy random strings. The refresh
 * token is only ever transported in an `httpOnly` cookie and is persisted as a
 * SHA-256 hash, so a database leak cannot yield a usable credential. The CSRF
 * token is a stateless double-submit value: it rides a JS-readable cookie and is
 * echoed back in a header; the server only checks the two match, so it is never
 * stored.
 */
final class RefreshTokenSecret
{
    /** Raw entropy in bytes for the opaque refresh/CSRF tokens. */
    private const TOKEN_BYTES = 32;

    /** A new opaque refresh-token plaintext (URL-safe, no padding). */
    public static function generateRaw(): string
    {
        return self::base64Url(random_bytes(self::TOKEN_BYTES));
    }

    /** A rotation-lineage identifier shared by every token in a family. */
    public static function generateFamilyId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** A double-submit CSRF value (returned to the client via a readable cookie). */
    public static function generateCsrfToken(): string
    {
        return self::base64Url(random_bytes(self::TOKEN_BYTES));
    }

    /** SHA-256 digest used as the stored/looked-up handle for a refresh token. */
    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
