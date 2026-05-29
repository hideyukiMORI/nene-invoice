<?php

declare(strict_types=1);

namespace NeneInvoice\Compliance;

/**
 * Single source of truth for the Japan invoice registration number (登録番号)
 * **syntax** rule: `T` followed by 13 digits. This validates format only — it
 * does not verify the number exists or compute a check digit
 * (see `docs/explanation/accounting-compliance.md` §4).
 *
 * Used by both the buyer (Client) and the issuer (CompanySettings).
 */
final class RegistrationNumber
{
    public const PATTERN = '/^T[0-9]{13}$/';

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }
}
