<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use NeneInvoice\Payment\ExportPaymentsCsvUseCase;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ExportPaymentsCsvUseCaseTest extends TestCase
{
    public function test_returns_empty_csv_with_header_when_no_payments(): void
    {
        $useCase = new ExportPaymentsCsvUseCase($this->fakeRepo([]));
        $csv     = $useCase->execute();

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('請求書番号', $csv);
        self::assertStringContainsString('入金日', $csv);
        self::assertStringContainsString('金額(円)', $csv);
    }

    public function test_csv_contains_payment_row(): void
    {
        $repo = $this->fakeRepo([
            [
                'invoice_number' => 'INV-2026-001',
                'client_name'    => '株式会社サンプル',
                'paid_at'        => '2026-05-20',
                'amount_cents'   => 11000,
                'method'         => 'bank_transfer',
                'note'           => '5月分',
            ],
        ]);

        $csv = (new ExportPaymentsCsvUseCase($repo))->execute();

        self::assertStringContainsString('INV-2026-001', $csv);
        self::assertStringContainsString('株式会社サンプル', $csv);
        self::assertStringContainsString('2026-05-20', $csv);
        self::assertStringContainsString('11000', $csv);
        self::assertStringContainsString('銀行振込', $csv);
        self::assertStringContainsString('5月分', $csv);
    }

    public function test_csv_uses_japanese_method_labels(): void
    {
        $methods = [
            ['method' => 'bank_transfer', 'expected' => '銀行振込'],
            ['method' => 'cash', 'expected' => '現金'],
            ['method' => 'other', 'expected' => 'その他'],
        ];

        foreach ($methods as ['method' => $method, 'expected' => $expected]) {
            $repo = $this->fakeRepo([
                [
                    'invoice_number' => 'INV-001', 'client_name' => 'Acme',
                    'paid_at' => '2026-05-01', 'amount_cents' => 5000,
                    'method' => $method, 'note' => '',
                ],
            ]);

            $csv = (new ExportPaymentsCsvUseCase($repo))->execute();
            self::assertStringContainsString($expected, $csv, "Method {$method} should map to {$expected}");
        }
    }

    /**
     * @param list<array{invoice_number: string, client_name: string, paid_at: string, amount_cents: int, method: string, note: string}> $rows
     */
    private function fakeRepo(array $rows): PaymentRepositoryInterface
    {
        return new class ($rows) implements PaymentRepositoryInterface {
            /** @var list<array{invoice_number: string, client_name: string, paid_at: string, amount_cents: int, method: string, note: string}> */
            private array $exportRows;

            /** @param list<array{invoice_number: string, client_name: string, paid_at: string, amount_cents: int, method: string, note: string}> $exportRows */
            public function __construct(array $exportRows)
            {
                $this->exportRows = $exportRows;
            }

            /** @return list<array{invoice_number: string, client_name: string, paid_at: string, amount_cents: int, method: string, note: string}> */
            public function findValidForExport(): array
            {
                return $this->exportRows;
            }

            public function save(Payment $payment): int
            {
                return 0;
            }
            public function findById(int $id): ?Payment
            {
                return null;
            }
            public function findByIdempotencyKey(string $key): ?Payment
            {
                return null;
            }
            public function markVoided(int $id): void
            {
            }
            /** @return list<Payment> */
            public function findByInvoice(int $invoiceId): array
            {
                return [];
            }
            public function totalPaidForInvoice(int $invoiceId): int
            {
                return 0;
            }
            /** @return array<int, int> */
            public function sumPaidForInvoices(array $invoiceIds): array
            {
                return [];
            }
            public function outstandingTotal(): int
            {
                return 0;
            }
            public function receivedTotalBetween(string $startInclusive, string $endExclusive): int
            {
                return 0;
            }
            /** @return array{current: int, overdue_1_30: int, overdue_31_plus: int} */
            public function agingBuckets(string $now, string $thirtyDaysAgo): array
            {
                return ['current' => 0, 'overdue_1_30' => 0, 'overdue_31_plus' => 0];
            }
        };
    }
}
