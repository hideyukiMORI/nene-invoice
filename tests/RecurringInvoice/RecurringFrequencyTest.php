<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use NeneInvoice\RecurringInvoice\RecurringFrequency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecurringFrequencyTest extends TestCase
{
    /**
     * Day-of-month is preserved and clamped to the target month's length; the
     * 31st of a long month lands on the last day of a shorter one.
     */
    #[DataProvider('nextRunCases')]
    public function test_next_run_date(RecurringFrequency $frequency, string $from, string $expected): void
    {
        self::assertSame($expected, $frequency->nextRunDate($from));
    }

    /** @return iterable<string, array{RecurringFrequency, string, string}> */
    public static function nextRunCases(): iterable
    {
        yield 'monthly mid-month'        => [RecurringFrequency::Monthly, '2026-06-15', '2026-07-15'];
        yield 'monthly 31st -> Feb clamp' => [RecurringFrequency::Monthly, '2026-01-31', '2026-02-28'];
        yield 'monthly Feb -> Mar'       => [RecurringFrequency::Monthly, '2026-02-28', '2026-03-28'];
        yield 'monthly year rollover'    => [RecurringFrequency::Monthly, '2026-12-10', '2027-01-10'];
        yield 'monthly 30th -> Feb clamp' => [RecurringFrequency::Monthly, '2026-03-30', '2026-04-30'];
        yield 'quarterly'                => [RecurringFrequency::Quarterly, '2026-01-15', '2026-04-15'];
        yield 'quarterly year rollover'  => [RecurringFrequency::Quarterly, '2026-11-30', '2027-02-28'];
    }
}
