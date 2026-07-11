<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use NeneInvoice\Demo\DemoDataSeeder;
use NeneInvoice\Demo\DemoTemplate;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every demo template must seed at least one quote so the 見積一覧 screen
 * shows the 見積→請求 lifecycle instead of an empty state (#651). `kensetsu`
 * already had quotes; `bldmainte` and `seisaku` did not.
 */
final class DemoDataSeederQuoteTest extends TestCase
{
    /** @return iterable<string, array{0: DemoTemplate}> */
    public static function templates(): iterable
    {
        foreach (DemoTemplate::cases() as $template) {
            yield $template->value => [$template];
        }
    }

    #[DataProvider('templates')]
    public function test_seeds_at_least_one_quote(DemoTemplate $template): void
    {
        $recorder = new RecordingQueryExecutor();
        $seeder   = new DemoDataSeeder($recorder, new FixedClock('2026-08-01T09:00:00Z'));
        $seeder->seed(1, $template);

        self::assertGreaterThanOrEqual(
            1,
            $recorder->insertCount('quotes'),
            "expected at least one seeded quote for {$template->value}",
        );
    }

    #[DataProvider('templates')]
    public function test_quote_document_sequence_matches_seeded_quote_count(DemoTemplate $template): void
    {
        $recorder = new RecordingQueryExecutor();
        $seeder   = new DemoDataSeeder($recorder, new FixedClock('2026-08-01T09:00:00Z'));
        $seeder->seed(1, $template);

        $sequenceRows = $recorder->columnValues('document_sequences', 'doc_type');
        $quoteRowCount = count(array_filter($sequenceRows, static fn (mixed $docType): bool => $docType === 'quote'));

        self::assertSame(1, $quoteRowCount, "expected exactly one quote document_sequences row for {$template->value}");
    }
}
