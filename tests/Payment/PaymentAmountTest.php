<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use Nene2\Validation\ValidationException;
use NeneInvoice\Payment\PaymentAmount;
use PHPUnit\Framework\TestCase;

final class PaymentAmountTest extends TestCase
{
    public function test_accepts_an_integer(): void
    {
        self::assertSame(1500, PaymentAmount::fromBody(['amount_cents' => 1500]));
    }

    public function test_accepts_a_pure_integer_string(): void
    {
        self::assertSame(1500, PaymentAmount::fromBody(['amount_cents' => '1500']));
    }

    public function test_rejects_a_float_instead_of_truncating(): void
    {
        // Round 4 F2: 100.5 must NOT become 100.
        $this->expectException(ValidationException::class);

        PaymentAmount::fromBody(['amount_cents' => 100.5]);
    }

    public function test_rejects_a_decimal_string(): void
    {
        $this->expectException(ValidationException::class);

        PaymentAmount::fromBody(['amount_cents' => '100.5']);
    }

    public function test_rejects_missing_amount(): void
    {
        $this->expectException(ValidationException::class);

        PaymentAmount::fromBody([]);
    }
}
