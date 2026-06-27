<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Compliance;

use NeneInvoice\Compliance\RegistrationNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegistrationNumberTest extends TestCase
{
    public function test_accepts_t_plus_13_digits(): void
    {
        self::assertTrue(RegistrationNumber::isValid('T1234567890123'));
    }

    public function test_rejects_malformed_values(): void
    {
        self::assertFalse(RegistrationNumber::isValid('1234567890123'));   // no T
        self::assertFalse(RegistrationNumber::isValid('T123456789012'));   // 12 digits
        self::assertFalse(RegistrationNumber::isValid('T12345678901234')); // 14 digits
        self::assertFalse(RegistrationNumber::isValid('TABCDEFGHIJKLM'));  // letters
        self::assertFalse(RegistrationNumber::isValid(''));
    }

    /**
     * Digit-count boundary around the required exactly-13. The valid case sits
     * at 13; every neighbour (12 below, 14 above, and the degenerate 0/1) fails.
     */
    #[DataProvider('digitCountCases')]
    public function test_digit_count_boundary(string $value, bool $expected): void
    {
        self::assertSame($expected, RegistrationNumber::isValid($value));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function digitCountCases(): iterable
    {
        yield 'T + 0 digits'  => ['T', false];
        yield 'T + 1 digit'   => ['T1', false];
        yield 'T + 12 digits' => ['T123456789012', false];
        yield 'T + 13 digits' => ['T1234567890123', true];
        yield 'T + 14 digits' => ['T12345678901234', false];
        yield 'T + 15 digits' => ['T123456789012345', false];
    }

    /**
     * Character-class and anchoring boundary: the prefix is a case-sensitive
     * uppercase T, the 13 body characters must all be ASCII digits, and the
     * `^...\z` anchors reject any surrounding whitespace — including a trailing
     * newline, which a `$` anchor would have let through (#500).
     */
    #[DataProvider('characterCases')]
    public function test_character_and_anchoring_boundary(string $value, bool $expected): void
    {
        self::assertSame($expected, RegistrationNumber::isValid($value));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function characterCases(): iterable
    {
        yield 'lowercase t prefix'    => ['t1234567890123', false];
        yield 'space inside digits'   => ['T123456 890123', false];
        yield 'hyphen inside digits'  => ['T123456-890123', false];
        yield 'dot inside digits'     => ['T123456.890123', false];
        yield 'letter inside digits'  => ['T12345678901X3', false];
        yield 'leading whitespace'    => [' T1234567890123', false];
        yield 'trailing whitespace'   => ['T1234567890123 ', false];
        yield 'trailing newline'      => ["T1234567890123\n", false];
        yield 'leading newline'       => ["\nT1234567890123", false];
        yield 'embedded newline'      => ["T1234567890123\n\n", false];
        yield 'all valid (control)'   => ['T0000000000000', true];
    }

    /**
     * Regression for #500: the `\z` tail anchor rejects a value with a trailing
     * newline. A `$` anchor matches before a final newline in PCRE, which used to
     * accept "T…\n"; this guards against that hardening being reverted.
     */
    public function test_trailing_newline_is_rejected(): void
    {
        self::assertFalse(RegistrationNumber::isValid("T1234567890123\n"));
    }
}
