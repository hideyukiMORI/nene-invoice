<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use NeneInvoice\Demo\DemoDataSeeder;
use NeneInvoice\Demo\DemoTemplate;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Seeded *historical* events (invoice/quote issue dates, payments, bank
 * transactions) must never post-date "today" — day-of-month anchors like
 * "the 15th" would otherwise produce future-dated receipts when the demo is
 * opened early in the month (#605). Due dates are deliberately not clamped.
 *
 * Runs the real seeder for every template against a recording executor, so
 * every INSERT of every template is inspected without a database.
 */
final class DemoDataSeederDateTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function clockDates(): iterable
    {
        yield 'first of month (worst case)' => ['2026-08-01T09:00:00Z', '2026-08-01'];
        yield 'mid month' => ['2026-08-20T09:00:00Z', '2026-08-20'];
    }

    #[DataProvider('clockDates')]
    public function test_historical_dates_never_exceed_today(string $clockNow, string $today): void
    {
        foreach (DemoTemplate::cases() as $template) {
            $recorder = new RecordingQueryExecutor();
            $seeder   = new DemoDataSeeder($recorder, new FixedClock($clockNow));
            $seeder->seed(1, $template);

            foreach ($recorder->columnValues('payments', 'paid_at') as $paidAt) {
                self::assertLessThanOrEqual($today, $paidAt, "future paid_at in {$template->value}");
            }
            foreach ($recorder->columnValues('bank_transactions', 'value_date') as $valueDate) {
                self::assertLessThanOrEqual($today, $valueDate, "future value_date in {$template->value}");
            }
            foreach (['invoices', 'quotes'] as $table) {
                foreach ($recorder->columnValues($table, 'issued_at') as $issuedAt) {
                    if ($issuedAt === null) {
                        continue; // drafts
                    }
                    self::assertLessThanOrEqual($today, $issuedAt, "future issued_at in {$table} ({$template->value})");
                }
            }
        }
    }

    public function test_due_dates_are_not_clamped(): void
    {
        // Early in the month, at least one seeded due date must still lie in the
        // future — the "due later this month" showcase must survive the clamp.
        $recorder = new RecordingQueryExecutor();
        $seeder   = new DemoDataSeeder($recorder, new FixedClock('2026-08-01T09:00:00Z'));
        $seeder->seed(1, DemoTemplate::Kensetsu);

        $futureDue = array_filter(
            $recorder->columnValues('invoices', 'due_at'),
            static fn (?string $due): bool => $due !== null && $due > '2026-08-01',
        );

        self::assertNotEmpty($futureDue);
    }
}
