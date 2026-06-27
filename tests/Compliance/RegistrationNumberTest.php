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
     * `^...$` anchors reject any surrounding whitespace (length stays 14).
     */
    #[DataProvider('characterCases')]
    public function test_character_and_anchoring_boundary(string $value, bool $expected): void
    {
        self::assertSame($expected, RegistrationNumber::isValid($value));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function characterCases(): iterable
    {
        yield 'lowercase t prefix'   => ['t1234567890123', false];
        yield 'space inside digits'  => ['T123456 890123', false];
        yield 'hyphen inside digits' => ['T123456-890123', false];
        yield 'dot inside digits'    => ['T123456.890123', false];
        yield 'letter inside digits' => ['T12345678901X3', false];
        yield 'leading whitespace'   => [' T1234567890123', false];
        yield 'trailing whitespace'  => ['T1234567890123 ', false];
        yield 'all valid (control)'  => ['T0000000000000', true];
    }

    /**
     * Known quirk, asserted to current behavior (not a desired outcome): the
     * pattern anchors the tail with `$`, which in PCRE matches *before* a single
     * trailing newline — so "T…\n" is accepted. Hardening to `\z` is a separate
     * production change (see follow-up issue). This test documents the boundary
     * so a future `\z` fix flips it deliberately rather than silently.
     */
    public function test_trailing_newline_is_currently_accepted(): void
    {
        self::assertTrue(RegistrationNumber::isValid("T1234567890123\n"));
        // A newline anywhere but the very end is still rejected.
        self::assertFalse(RegistrationNumber::isValid("T1234567890123\n\n"));
        self::assertFalse(RegistrationNumber::isValid("\nT1234567890123"));
    }
}
