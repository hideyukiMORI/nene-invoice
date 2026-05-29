<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Compliance;

use NeneInvoice\Compliance\RegistrationNumber;
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
}
