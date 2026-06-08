<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\User;

use Nene2\Validation\ValidationException;
use NeneInvoice\User\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function test_accepts_a_password_at_the_minimum_length(): void
    {
        PasswordPolicy::assert(str_repeat('a', PasswordPolicy::MIN_LENGTH));

        $this->expectNotToPerformAssertions();
    }

    public function test_rejects_a_password_below_the_minimum_length(): void
    {
        $this->expectException(ValidationException::class);

        PasswordPolicy::assert(str_repeat('a', PasswordPolicy::MIN_LENGTH - 1));
    }

    public function test_rejects_a_password_above_the_maximum_length(): void
    {
        $this->expectException(ValidationException::class);

        PasswordPolicy::assert(str_repeat('a', PasswordPolicy::MAX_LENGTH + 1));
    }

    public function test_counts_multibyte_characters_by_codepoint(): void
    {
        // 11 multibyte chars < MIN_LENGTH (12) — must be rejected, not pass on byte length.
        $this->expectException(ValidationException::class);

        PasswordPolicy::assert(str_repeat('あ', PasswordPolicy::MIN_LENGTH - 1));
    }
}
