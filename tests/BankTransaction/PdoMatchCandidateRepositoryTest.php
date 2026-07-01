<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\PdoMatchCandidateRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoMatchCandidateRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private PdoMatchCandidateRepository $repository;

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

        foreach (['invoices', 'payments', 'clients'] as $table) {
            $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/' . $table . '.sql');
            self::assertIsString($schema);
            $pdo->exec($schema);
        }

        $this->seed($pdo);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new PdoMatchCandidateRepository(
            new PdoDatabaseQueryExecutor($factory, $pdo),
            $this->holder,
        );
    }

    private function seed(PDO $pdo): void
    {
        $now = '2026-06-01 00:00:00';

        $pdo->exec("INSERT INTO clients (id, organization_id, name, name_kana, created_at, updated_at) VALUES
            (7, 1, 'サンプル製作所', 'サンプルセイサクシヨ', '{$now}', '{$now}'),
            (8, 1, 'べつ商店', 'ベツシヨウテン', '{$now}', '{$now}'),
            (9, 2, 'よそのorg', 'ヨソノオルグ', '{$now}', '{$now}')");

        // 100: issued, outstanding 11000 (a voided 1000 payment must not reduce it)
        // 101: partially_paid, 20000 - 5000 = 15000 outstanding
        // 102: paid -> excluded by status
        // 103: draft -> excluded by status
        // 104: issued but fully paid (5000/5000) -> excluded by HAVING > 0
        // 200: org 2 -> excluded by org scope
        $pdo->exec("INSERT INTO invoices (id, organization_id, client_id, status, invoice_number, subtotal_cents, tax_cents, total_cents, is_qualified_invoice, is_deleted, created_at, updated_at) VALUES
            (100, 1, 7, 'issued',         'INV-100', 11000, 0, 11000, 0, 0, '{$now}', '{$now}'),
            (101, 1, 8, 'partially_paid', 'INV-101', 20000, 0, 20000, 0, 0, '{$now}', '{$now}'),
            (102, 1, 7, 'paid',           'INV-102',  3000, 0,  3000, 0, 0, '{$now}', '{$now}'),
            (103, 1, 7, 'draft',          NULL,       4000, 0,  4000, 0, 0, '{$now}', '{$now}'),
            (104, 1, 8, 'issued',         'INV-104',  5000, 0,  5000, 0, 0, '{$now}', '{$now}'),
            (200, 2, 9, 'issued',         'INV-200',  9999, 0,  9999, 0, 0, '{$now}', '{$now}')");

        $pdo->exec("INSERT INTO payments (id, organization_id, invoice_id, amount_cents, paid_at, is_deleted, created_at, updated_at) VALUES
            (1, 1, 100, 1000, '{$now}', 1, '{$now}', '{$now}'),
            (2, 1, 101, 5000, '{$now}', 0, '{$now}', '{$now}'),
            (3, 1, 104, 5000, '{$now}', 0, '{$now}', '{$now}')");
    }

    public function test_returns_only_open_receivables_with_outstanding_ordered_by_id(): void
    {
        $receivables = $this->repository->findOpenReceivables();

        self::assertCount(2, $receivables);
        self::assertSame([100, 101], array_map(static fn ($r): int => $r->invoiceId, $receivables));
    }

    public function test_outstanding_ignores_voided_payments_and_subtracts_valid_ones(): void
    {
        $receivables = $this->repository->findOpenReceivables();

        self::assertSame(11000, $receivables[0]->outstandingCents); // voided 1000 ignored
        self::assertSame(15000, $receivables[1]->outstandingCents); // 20000 - 5000
    }

    public function test_projects_invoice_number_and_client_names(): void
    {
        $first = $this->repository->findOpenReceivables()[0];

        self::assertSame('INV-100', $first->invoiceNumber);
        self::assertSame('サンプル製作所', $first->clientName);
        self::assertSame('サンプルセイサクシヨ', $first->clientNameKana);
        self::assertSame('サンプルセイサクシヨ', $first->matchName());
    }

    public function test_is_org_scoped(): void
    {
        $this->holder->set(2);

        $receivables = $this->repository->findOpenReceivables();

        self::assertCount(1, $receivables);
        self::assertSame(200, $receivables[0]->invoiceId);
    }
}
