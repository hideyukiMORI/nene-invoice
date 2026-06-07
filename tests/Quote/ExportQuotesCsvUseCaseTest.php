<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use NeneInvoice\Quote\ExportQuotesCsvUseCase;
use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteListFilter;
use NeneInvoice\Quote\QuoteRepositoryInterface;
use NeneInvoice\Quote\QuoteSort;
use PHPUnit\Framework\TestCase;

final class ExportQuotesCsvUseCaseTest extends TestCase
{
    public function test_returns_empty_csv_with_header_when_no_quotes(): void
    {
        $csv = (new ExportQuotesCsvUseCase($this->fakeRepo([])))->execute(new QuoteListFilter());

        // UTF-8 BOM
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('見積番号', $csv);
        self::assertStringContainsString('有効期限', $csv);
    }

    public function test_csv_contains_quote_row(): void
    {
        $repo = $this->fakeRepo([
            [
                'quote_number'   => 'EST-2026-001',
                'issued_at'      => '2026-05-01',
                'valid_until'    => '2026-05-31',
                'client_name'    => '株式会社サンプル',
                'subtotal_cents' => 10000,
                'tax_cents'      => 1000,
                'total_cents'    => 11000,
                'status'         => 'sent',
            ],
        ]);

        $csv = (new ExportQuotesCsvUseCase($repo))->execute(new QuoteListFilter());

        self::assertStringContainsString('EST-2026-001', $csv);
        self::assertStringContainsString('株式会社サンプル', $csv);
        self::assertStringContainsString('11000', $csv);
        self::assertStringContainsString('送付済み', $csv);
    }

    public function test_csv_uses_japanese_status_labels(): void
    {
        $statuses = [
            ['status' => 'draft', 'expected' => '下書き'],
            ['status' => 'sent', 'expected' => '送付済み'],
            ['status' => 'accepted', 'expected' => '承認済み'],
            ['status' => 'rejected', 'expected' => '却下'],
            ['status' => 'expired', 'expected' => '期限切れ'],
        ];

        foreach ($statuses as ['status' => $status, 'expected' => $expected]) {
            $repo = $this->fakeRepo([
                [
                    'quote_number' => 'EST-001', 'issued_at' => '2026-05-01',
                    'valid_until' => '2026-05-31', 'client_name' => 'Acme',
                    'subtotal_cents' => 1000, 'tax_cents' => 100, 'total_cents' => 1100,
                    'status' => $status,
                ],
            ]);

            $csv = (new ExportQuotesCsvUseCase($repo))->execute(new QuoteListFilter());
            self::assertStringContainsString($expected, $csv, "Status {$status} should map to {$expected}");
        }
    }

    public function test_blank_issued_at_renders_empty_not_today(): void
    {
        $repo = $this->fakeRepo([
            [
                'quote_number' => 'EST-DRAFT', 'issued_at' => '',
                'valid_until' => '', 'client_name' => 'Acme',
                'subtotal_cents' => 1000, 'tax_cents' => 100, 'total_cents' => 1100,
                'status' => 'draft',
            ],
        ]);

        $csv   = (new ExportQuotesCsvUseCase($repo))->execute(new QuoteListFilter());
        $today = (new \DateTimeImmutable('now'))->format('Y-m-d');

        // A blank issue date must stay blank — never silently become today.
        self::assertStringNotContainsString($today, $csv);
    }

    /**
     * @param list<array{quote_number: string, issued_at: string|null, valid_until: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string}> $rows
     */
    private function fakeRepo(array $rows): QuoteRepositoryInterface
    {
        return new class ($rows) implements QuoteRepositoryInterface {
            /** @var list<array{quote_number: string, issued_at: string|null, valid_until: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string}> */
            private array $exportRows;

            /** @param list<array{quote_number: string, issued_at: string|null, valid_until: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string}> $exportRows */
            public function __construct(array $exportRows)
            {
                $this->exportRows = $exportRows;
            }

            /** @return list<array{quote_number: string, issued_at: string|null, valid_until: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string}> */
            public function findForExport(QuoteListFilter $filter): array
            {
                return $this->exportRows;
            }

            public function findById(int $id): ?Quote
            {
                return null;
            }
            /** @return list<\NeneInvoice\Quote\QuoteListRow> */
            public function findForAdminList(QuoteListFilter $filter, QuoteSort $sort, int $limit, int $offset): array
            {
                return [];
            }
            public function countForAdminList(QuoteListFilter $filter): int
            {
                return 0;
            }
            public function save(Quote $quote): int
            {
                return 0;
            }
            public function update(Quote $quote): void
            {
            }
            public function delete(int $id): void
            {
            }
        };
    }
}
