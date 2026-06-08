<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Minimum password policy for operator accounts (Round 3 finding L-2).
 *
 * Operator/admin accounts gate the whole admin surface, so a length floor is
 * enforced at the HTTP boundary. The upper bound is a sanity cap against
 * pathological input (note: bcrypt itself only consumes the first 72 bytes).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 256;

    /**
     * Validates a plaintext password, throwing a {@see ValidationException} with
     * the given field path when it violates the policy.
     *
     * @throws ValidationException
     */
    public static function assert(string $password, string $field = 'body.password'): void
    {
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            throw new ValidationException([new ValidationError(
                $field,
                sprintf('Password must be at least %d characters.', self::MIN_LENGTH),
                'too_short',
            )]);
        }

        if ($length > self::MAX_LENGTH) {
            throw new ValidationException([new ValidationError(
                $field,
                sprintf('Password must be at most %d characters.', self::MAX_LENGTH),
                'too_long',
            )]);
        }
    }
}
