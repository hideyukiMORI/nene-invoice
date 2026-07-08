<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
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

/**
 * Records every INSERT and exposes values by (table, column), resolved from the
 * statement's own column list — no hard-coded parameter indexes.
 */
final class RecordingQueryExecutor implements DatabaseQueryExecutorInterface
{
    /** @var list<array{sql: string, parameters: array<int|string, mixed>}> */
    private array $inserts = [];

    private int $nextId = 1;

    public function execute(string $sql, array $parameters = []): int
    {
        $this->record($sql, $parameters);

        return 1;
    }

    public function insert(string $sql, array $parameters = []): int
    {
        $this->record($sql, $parameters);

        return $this->nextId++;
    }

    public function lastInsertId(): int
    {
        return $this->nextId - 1;
    }

    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        return null;
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        return [];
    }

    /** @return list<mixed> Values bound to $column across all INSERTs into $table. */
    public function columnValues(string $table, string $column): array
    {
        $values = [];

        foreach ($this->inserts as $insert) {
            if (preg_match('/INSERT INTO ' . $table . '\s*\(([^)]+)\)/i', $insert['sql'], $m) !== 1) {
                continue;
            }
            $columns = array_map('trim', explode(',', $m[1]));
            $index   = array_search($column, $columns, true);
            if ($index === false) {
                continue;
            }
            // Positional parameters line up with the column list only when every
            // VALUES entry is a placeholder; the seeder embeds literals (e.g.
            // is_deleted 0), so count placeholders up to the column's position.
            if (preg_match('/VALUES\s*\((.+)\)/is', $insert['sql'], $vm) !== 1) {
                continue;
            }
            $slots       = array_map('trim', explode(',', $vm[1]));
            $paramIndex  = 0;
            foreach ($slots as $slotPosition => $slot) {
                if ($slotPosition === $index) {
                    $values[] = $slot === '?' ? ($insert['parameters'][$paramIndex] ?? null) : $slot;
                    break;
                }
                if ($slot === '?') {
                    $paramIndex++;
                }
            }
        }

        return $values;
    }

    /** @param array<int|string, mixed> $parameters */
    private function record(string $sql, array $parameters): void
    {
        if (stripos(ltrim($sql), 'INSERT') === 0) {
            $this->inserts[] = ['sql' => $sql, 'parameters' => $parameters];
        }
    }
}
