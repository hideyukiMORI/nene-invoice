<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use NeneInvoice\Invoice\ExportInvoicesCsvUseCase;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceListFilter;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ExportInvoicesCsvUseCaseTest extends TestCase
{
    public function test_returns_empty_csv_with_header_when_no_invoices(): void
    {
        $useCase = new ExportInvoicesCsvUseCase($this->fakeRepo([]));
        $csv     = $useCase->execute();

        // UTF-8 BOM
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('請求書番号', $csv);
        self::assertStringContainsString('取引先', $csv);
    }

    public function test_csv_contains_invoice_row(): void
    {
        $repo = $this->fakeRepo([
            [
                'invoice_number'       => 'INV-2026-001',
                'issued_at'            => '2026-05-01',
                'due_at'               => '2026-05-31',
                'client_name'          => '株式会社サンプル',
                'subtotal_cents'       => 10000,
                'tax_cents'            => 1000,
                'total_cents'          => 11000,
                'status'               => 'issued',
                'is_qualified_invoice' => true,
            ],
        ]);

        $csv = (new ExportInvoicesCsvUseCase($repo))->execute();

        self::assertStringContainsString('INV-2026-001', $csv);
        self::assertStringContainsString('株式会社サンプル', $csv);
        self::assertStringContainsString('11000', $csv);
        self::assertStringContainsString('発行済み', $csv);
        self::assertStringContainsString('はい', $csv);
    }

    public function test_csv_uses_japanese_status_labels(): void
    {
        $statuses = [
            ['status' => 'issued', 'expected' => '発行済み'],
            ['status' => 'partially_paid', 'expected' => '一部入金'],
            ['status' => 'paid', 'expected' => '入金済み'],
        ];

        foreach ($statuses as ['status' => $status, 'expected' => $expected]) {
            $repo = $this->fakeRepo([
                [
                    'invoice_number' => 'INV-001', 'issued_at' => '2026-05-01',
                    'due_at' => '2026-05-31', 'client_name' => 'Acme',
                    'subtotal_cents' => 1000, 'tax_cents' => 100, 'total_cents' => 1100,
                    'status' => $status, 'is_qualified_invoice' => false,
                ],
            ]);

            $csv = (new ExportInvoicesCsvUseCase($repo))->execute();
            self::assertStringContainsString($expected, $csv, "Status {$status} should map to {$expected}");
        }
    }

    public function test_non_qualified_invoice_shows_iie(): void
    {
        $repo = $this->fakeRepo([
            [
                'invoice_number' => 'INV-001', 'issued_at' => '2026-05-01',
                'due_at' => '2026-05-31', 'client_name' => 'Acme',
                'subtotal_cents' => 1000, 'tax_cents' => 100, 'total_cents' => 1100,
                'status' => 'issued', 'is_qualified_invoice' => false,
            ],
        ]);

        $csv = (new ExportInvoicesCsvUseCase($repo))->execute();
        self::assertStringContainsString('いいえ', $csv);
    }

    /**
     * @param list<array{invoice_number: string, issued_at: string, due_at: string, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string, is_qualified_invoice: bool}> $rows
     */
    private function fakeRepo(array $rows): InvoiceRepositoryInterface
    {
        return new class ($rows) implements InvoiceRepositoryInterface {
            /** @var list<array{invoice_number: string, issued_at: string|null, due_at: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string, is_qualified_invoice: bool}> */
            private array $exportRows;

            /** @param list<array{invoice_number: string, issued_at: string|null, due_at: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string, is_qualified_invoice: bool}> $exportRows */
            public function __construct(array $exportRows)
            {
                $this->exportRows = $exportRows;
            }

            /** @return list<array{invoice_number: string, issued_at: string|null, due_at: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string, is_qualified_invoice: bool}> */
            public function findIssuedForExport(): array
            {
                return $this->exportRows;
            }

            public function findById(int $id): ?Invoice
            {
                return null;
            }
            public function existsForQuote(int $quoteId): bool
            {
                return false;
            }
            /** @return list<Invoice> */
            public function findAll(int $limit, int $offset): array
            {
                return [];
            }
            public function count(): int
            {
                return 0;
            }
            /** @return list<Invoice> */
            public function findFiltered(InvoiceListFilter $filter, int $limit, int $offset): array
            {
                return [];
            }
            public function countFiltered(InvoiceListFilter $filter): int
            {
                return 0;
            }
            /** @return list<\NeneInvoice\Invoice\InvoiceListRow> */
            public function findForAdminList(InvoiceListFilter $filter, \NeneInvoice\Invoice\InvoiceSort $sort, int $limit, int $offset): array
            {
                return [];
            }
            public function countForAdminList(InvoiceListFilter $filter): int
            {
                return 0;
            }
            /** @return array{unpaid_count: int, overdue_count: int, recent_unpaid: list<Invoice>} */
            public function getDashboardData(string $now): array
            {
                return ['unpaid_count' => 0, 'overdue_count' => 0, 'recent_unpaid' => []];
            }
            public function billedTotalBetween(string $startInclusive, string $endExclusive): array
            {
                return ['cents' => 0, 'count' => 0];
            }
            public function save(Invoice $invoice): int
            {
                return 0;
            }
            public function update(Invoice $invoice): void
            {
            }
            public function delete(int $id): void
            {
            }
        };
    }
}
