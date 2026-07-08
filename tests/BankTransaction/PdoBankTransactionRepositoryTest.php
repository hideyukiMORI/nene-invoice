<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\BankTransaction;
use NeneInvoice\BankTransaction\BankTransactionDirection;
use NeneInvoice\BankTransaction\BankTransactionNotFoundException;
use NeneInvoice\BankTransaction\BankTransactionStatus;
use NeneInvoice\BankTransaction\PdoBankTransactionRepository;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PdoBankTransactionRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private PdoBankTransactionRepository $repository;

    protected function setUp(): void
    {
        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: ':memory:',
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $factory = new PdoConnectionFactory($config);
        $pdo     = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/bank_transactions.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new PdoBankTransactionRepository(
            new PdoDatabaseQueryExecutor($factory, $pdo),
            $this->holder,
            new FixedClock(),
        );
    }

    private function line(
        string $valueDate = '2026-06-30',
        int $amountCents = 11000,
        ?string $payerName = 'カ）サンプルセイサクシヨ',
        ?string $bankReference = 'TXN-0001',
        BankTransactionDirection $direction = BankTransactionDirection::Credit,
    ): BankTransaction {
        return new BankTransaction(
            organizationId: 1,
            valueDate: $valueDate,
            direction: $direction,
            amountCents: $amountCents,
            payerName: $payerName,
            description: '振込',
            bankReference: $bankReference,
        );
    }

    public function test_save_and_find_round_trips_all_fields(): void
    {
        $id = $this->repository->save($this->line());

        $found = $this->repository->findById($id);
        self::assertNotNull($found);
        self::assertSame($id, $found->id);
        self::assertSame(1, $found->organizationId);
        self::assertSame('2026-06-30', $found->valueDate);
        self::assertSame(BankTransactionDirection::Credit, $found->direction);
        self::assertSame(11000, $found->amountCents);
        self::assertSame('カ）サンプルセイサクシヨ', $found->payerName);
        self::assertSame('振込', $found->description);
        self::assertSame('TXN-0001', $found->bankReference);
        self::assertSame(BankTransactionStatus::Unmatched, $found->status);
        self::assertNull($found->matchedInvoiceId);
        self::assertNull($found->matchedPaymentId);
        self::assertNotNull($found->importedAt);
    }

    public function test_list_is_newest_value_date_first_with_count(): void
    {
        $this->repository->save($this->line(valueDate: '2026-06-01', bankReference: 'A'));
        $this->repository->save($this->line(valueDate: '2026-06-30', bankReference: 'B'));
        $this->repository->save($this->line(valueDate: '2026-06-15', bankReference: 'C'));

        $rows = $this->repository->findByOrganization(10, 0);
        self::assertCount(3, $rows);
        self::assertSame('2026-06-30', $rows[0]->valueDate);
        self::assertSame('2026-06-15', $rows[1]->valueDate);
        self::assertSame('2026-06-01', $rows[2]->valueDate);
        self::assertSame(3, $this->repository->countByOrganization());
    }

    public function test_reads_are_scoped_to_the_organization(): void
    {
        $id = $this->repository->save($this->line());

        $this->holder->set(2);
        self::assertNull($this->repository->findById($id));
        self::assertSame([], $this->repository->findByOrganization(10, 0));
        self::assertSame(0, $this->repository->countByOrganization());
        self::assertNull($this->repository->findByBankReference('TXN-0001'));
    }

    public function test_find_by_bank_reference_supports_idempotent_import(): void
    {
        $this->repository->save($this->line(bankReference: 'TXN-0001'));

        self::assertNotNull($this->repository->findByBankReference('TXN-0001'));
        self::assertNull($this->repository->findByBankReference('TXN-9999'));
    }

    public function test_update_changes_status_and_match(): void
    {
        $id    = $this->repository->save($this->line());
        $saved = $this->repository->findById($id);
        self::assertNotNull($saved);

        $this->repository->update(new BankTransaction(
            organizationId: $saved->organizationId,
            valueDate: $saved->valueDate,
            direction: $saved->direction,
            amountCents: $saved->amountCents,
            payerName: $saved->payerName,
            description: $saved->description,
            bankReference: $saved->bankReference,
            status: BankTransactionStatus::Posted,
            matchedInvoiceId: 42,
            matchedPaymentId: 7,
            importedAt: $saved->importedAt,
            id: $saved->id,
            createdAt: $saved->createdAt,
            updatedAt: $saved->updatedAt,
        ));

        $updated = $this->repository->findById($id);
        self::assertNotNull($updated);
        self::assertSame(BankTransactionStatus::Posted, $updated->status);
        self::assertSame(42, $updated->matchedInvoiceId);
        self::assertSame(7, $updated->matchedPaymentId);
    }

    public function test_update_missing_row_throws(): void
    {
        $this->expectException(BankTransactionNotFoundException::class);

        $this->repository->update(new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-30',
            direction: BankTransactionDirection::Credit,
            amountCents: 1000,
            id: 999,
        ));
    }
}
