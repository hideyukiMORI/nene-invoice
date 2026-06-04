<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Company;

use DateTimeImmutable;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Company\PaymentTerms;
use PHPUnit\Framework\TestCase;

final class PaymentTermsTest extends TestCase
{
    private function due(?int $closing, int $offset, ?int $pay, string $issue): string
    {
        return (new PaymentTerms($closing, $offset, $pay))->dueDateFrom(new DateTimeImmutable($issue));
    }

    public function test_month_end_close_next_month_end_pay(): void
    {
        // 月末締め翌月末払い
        self::assertSame('2026-05-31', $this->due(null, 1, null, '2026-04-15'));
        self::assertSame('2026-05-31', $this->due(null, 1, null, '2026-04-30'));
    }

    public function test_day20_close_rolls_to_next_cycle_after_closing(): void
    {
        // 20日締め翌月末払い。発行が締め日以前は当月締め、超過は翌月締め。
        self::assertSame('2026-05-31', $this->due(20, 1, null, '2026-04-10')); // 4/20締→5月末
        self::assertSame('2026-06-30', $this->due(20, 1, null, '2026-04-25')); // 5/20締→6月末
    }

    public function test_specific_pay_day(): void
    {
        // 20日締め翌月10日払い
        self::assertSame('2026-05-10', $this->due(20, 1, 10, '2026-04-10'));
    }

    public function test_pay_day_is_clamped_to_short_month(): void
    {
        // 末日払いが2月にかかる場合は28/29日へクランプ
        self::assertSame('2026-02-28', $this->due(null, 0, 31, '2026-02-10'));
        self::assertSame('2024-02-29', $this->due(null, 0, 31, '2024-02-05')); // うるう年
    }

    public function test_two_month_offset(): void
    {
        // 月末締め翌々月末払い
        self::assertSame('2026-06-30', $this->due(null, 2, null, '2026-04-15'));
    }

    public function test_company_settings_helpers(): void
    {
        $settings = new CompanySettings(
            organizationId: 1,
            legalName: 'X',
            defaultQuoteValidityDays: 30,
            defaultPaymentClosingDay: null,
            defaultPaymentMonthOffset: 1,
            defaultPaymentPayDay: null,
        );

        $terms = $settings->paymentTerms();
        self::assertNotNull($terms);
        self::assertSame('2026-05-31', $terms->dueDateFrom(new DateTimeImmutable('2026-04-15')));
        self::assertSame('2026-05-15', $settings->quoteValidUntilFrom(new DateTimeImmutable('2026-04-15')));
    }

    public function test_no_defaults_return_null(): void
    {
        $settings = new CompanySettings(organizationId: 1, legalName: 'X');
        self::assertNull($settings->paymentTerms());
        self::assertNull($settings->quoteValidUntilFrom(new DateTimeImmutable('2026-04-15')));
    }
}
