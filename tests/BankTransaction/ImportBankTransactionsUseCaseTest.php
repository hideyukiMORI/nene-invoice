<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\BankCsvColumnMapping;
use NeneInvoice\BankTransaction\ImportBankTransactionsUseCase;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryBankTransactionRepository;
use PHPUnit\Framework\TestCase;

final class ImportBankTransactionsUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryBankTransactionRepository $repository;
    private ImportBankTransactionsUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new InMemoryBankTransactionRepository($this->holder);
        $this->useCase    = new ImportBankTransactionsUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->repository,
        );
    }

    private function mapping(): BankCsvColumnMapping
    {
        return new BankCsvColumnMapping(
            valueDateColumn: 0,
            dateFormat: 'Y-m-d',
            amountColumn: 1,
            payerNameColumn: 2,
            descriptionColumn: 3,
            bankReferenceColumn: 4,
        );
    }

    public function test_imports_rows_and_scopes_them_to_the_org(): void
    {
        $csv = "日付,金額,依頼人,摘要,取引ID\n2026-06-30,11000,カ）A,振込,TX1\n2026-06-29,5000,カ）B,振込,TX2\n";

        $result = $this->useCase->execute($csv, $this->mapping());

        self::assertNull($result->formatError);
        self::assertSame(2, $result->importedCount);
        self::assertSame(0, $result->skippedDuplicateCount);
        self::assertSame(2, $this->repository->countByOrganization());

        $this->holder->set(2);
        self::assertSame(0, $this->repository->countByOrganization()); // org-scoped
    }

    public function test_reimport_skips_already_imported_references(): void
    {
        $csv = "日付,金額,依頼人,摘要,取引ID\n2026-06-30,11000,カ）A,振込,TX1\n";

        self::assertSame(1, $this->useCase->execute($csv, $this->mapping())->importedCount);

        $second = $this->useCase->execute($csv, $this->mapping());
        self::assertSame(0, $second->importedCount);
        self::assertSame(1, $second->skippedDuplicateCount);
        self::assertSame(1, $this->repository->countByOrganization()); // no duplicate
    }

    public function test_duplicate_reference_within_one_file_is_skipped(): void
    {
        $csv = "日付,金額,依頼人,摘要,取引ID\n2026-06-30,11000,カ）A,振込,TX1\n2026-06-30,11000,カ）A,振込,TX1\n";

        $result = $this->useCase->execute($csv, $this->mapping());

        self::assertSame(1, $result->importedCount);
        self::assertSame(1, $result->skippedDuplicateCount);
    }

    public function test_format_error_imports_nothing(): void
    {
        $result = $this->useCase->execute("日付\n2026-06-30\n", new BankCsvColumnMapping(valueDateColumn: 0));

        self::assertNotNull($result->formatError);
        self::assertSame(0, $result->importedCount);
        self::assertSame(0, $this->repository->countByOrganization());
    }
}
