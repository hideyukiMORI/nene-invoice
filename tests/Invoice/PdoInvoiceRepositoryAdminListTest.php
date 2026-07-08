<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceListFilter;
use NeneInvoice\Invoice\InvoiceSort;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\PdoInvoiceRepository;
use NeneInvoice\Tests\Support\FixedClock;
use PDO;
use PHPUnit\Framework\TestCase;

/** Real-DB coverage for the admin list query (joins clients for search/sort). */
final class PdoInvoiceRepositoryAdminListTest extends TestCase
{
    private PdoInvoiceRepository $repo;
    private PDO $pdo;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

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

        foreach (['clients', 'invoices'] as $table) {
            $sql = file_get_contents(dirname(__DIR__, 2) . "/database/schema/{$table}.sql");
            self::assertIsString($sql);
            $pdo->exec($sql);
        }

        $this->pdo    = $pdo;
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new PdoInvoiceRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder, new FixedClock());
    }

    private function client(int $id, string $name): void
    {
        $this->pdo->exec(sprintf(
            "INSERT INTO clients (id, organization_id, name, created_at, updated_at)
             VALUES (%d, 1, '%s', '2026-05-01 00:00:00', '2026-05-01 00:00:00')",
            $id,
            $name,
        ));
    }

    private function invoice(string $number, int $clientId, int $total, string $status, string $due): void
    {
        $this->repo->save(new Invoice(
            organizationId: 1,
            clientId: $clientId,
            status: InvoiceStatus::from($status),
            subtotalCents: $total,
            taxCents: 0,
            totalCents: $total,
            invoiceNumber: $number,
            issuedAt: '2026-05-01',
            dueAt: $due,
        ));
    }

    public function test_searches_by_number_or_client_name_and_returns_client_name(): void
    {
        $this->client(1, 'アルファ商事');
        $this->client(2, 'ベータ工業');
        $this->invoice('INV-001', 1, 100000, 'issued', '2026-06-30');
        $this->invoice('INV-002', 2, 200000, 'paid', '2026-07-31');

        $byName = $this->repo->findForAdminList(new InvoiceListFilter(search: 'アルファ'), new InvoiceSort(), 20, 0);
        self::assertCount(1, $byName);
        self::assertSame('INV-001', $byName[0]->invoice->invoiceNumber);
        self::assertSame('アルファ商事', $byName[0]->clientName);

        $byNumber = $this->repo->findForAdminList(new InvoiceListFilter(search: 'INV-002'), new InvoiceSort(), 20, 0);
        self::assertCount(1, $byNumber);
        self::assertSame('ベータ工業', $byNumber[0]->clientName);
    }

    public function test_filters_by_status_amount_and_due_range(): void
    {
        $this->client(1, 'A');
        $this->invoice('INV-001', 1, 100000, 'issued', '2026-06-30');
        $this->invoice('INV-002', 1, 500000, 'paid', '2026-07-31');

        self::assertSame(1, $this->repo->countForAdminList(new InvoiceListFilter(statuses: ['paid'])));
        self::assertSame(1, $this->repo->countForAdminList(new InvoiceListFilter(totalMin: 200000)));
        self::assertSame(1, $this->repo->countForAdminList(new InvoiceListFilter(totalMax: 200000)));
        self::assertSame(1, $this->repo->countForAdminList(new InvoiceListFilter(dueFrom: '2026-07-01')));
        self::assertSame(1, $this->repo->countForAdminList(new InvoiceListFilter(dueTo: '2026-07-01')));
    }

    public function test_sorts_by_total_ascending_and_descending(): void
    {
        $this->client(1, 'A');
        $this->invoice('INV-001', 1, 300000, 'issued', '2026-06-30');
        $this->invoice('INV-002', 1, 100000, 'issued', '2026-07-31');
        $this->invoice('INV-003', 1, 200000, 'issued', '2026-08-31');

        $asc = $this->repo->findForAdminList(new InvoiceListFilter(), new InvoiceSort('total', false), 20, 0);
        self::assertSame([100000, 200000, 300000], array_map(static fn ($r): int => $r->invoice->totalCents, $asc));

        $desc = $this->repo->findForAdminList(new InvoiceListFilter(), new InvoiceSort('total', true), 20, 0);
        self::assertSame([300000, 200000, 100000], array_map(static fn ($r): int => $r->invoice->totalCents, $desc));
    }

    public function test_overdue_only_excludes_paid_and_future(): void
    {
        $this->client(1, 'A');
        $this->invoice('INV-OLD', 1, 100000, 'issued', '2020-01-01');
        $this->invoice('INV-FUT', 1, 100000, 'issued', '2999-01-01');
        $this->invoice('INV-PAID', 1, 100000, 'paid', '2020-01-01');

        $rows = $this->repo->findForAdminList(
            new InvoiceListFilter(overdueOnly: true, today: '2026-06-04'),
            new InvoiceSort(),
            20,
            0,
        );
        self::assertCount(1, $rows);
        self::assertSame('INV-OLD', $rows[0]->invoice->invoiceNumber);
    }

    public function test_filters_by_issued_date_range_inclusive(): void
    {
        $this->client(1, 'A');
        $this->invoiceIssued('INV-APR', 1, 'issued', '2026-04-15');
        $this->invoiceIssued('INV-JUN-LO', 1, 'issued', '2026-06-01');
        $this->invoiceIssued('INV-JUN-HI', 1, 'issued', '2026-06-30');
        $this->invoiceIssued('INV-JUL', 1, 'issued', '2026-07-01');

        // The Q1-FY range 2026-06-01..2026-06-30 includes both boundary dates.
        $filter = new InvoiceListFilter(issuedFrom: '2026-06-01', issuedTo: '2026-06-30');
        $rows   = $this->repo->findForAdminList($filter, new InvoiceSort('number', false), 20, 0);

        self::assertSame(
            ['INV-JUN-HI', 'INV-JUN-LO'],
            array_map(static fn ($r): ?string => $r->invoice->invoiceNumber, $rows),
        );
        self::assertSame(2, $this->repo->countForAdminList($filter));
    }

    public function test_export_reflects_filter_and_excludes_drafts(): void
    {
        $this->client(1, 'アルファ商事');
        $this->invoiceIssued('INV-MAY', 1, 'issued', '2026-05-20');
        $this->invoiceIssued('INV-JUN', 1, 'paid', '2026-06-10');
        $this->invoiceIssued('INV-DRAFT', 1, 'draft', '2026-06-15');

        // Draft is excluded even though it falls inside the requested range.
        $rows = $this->repo->findIssuedForExport(
            new InvoiceListFilter(issuedFrom: '2026-06-01', issuedTo: '2026-06-30'),
        );

        self::assertCount(1, $rows);
        self::assertSame('INV-JUN', $rows[0]['invoice_number']);
        self::assertSame('アルファ商事', $rows[0]['client_name']);
        self::assertSame('2026-06-10', $rows[0]['issued_at']);
    }

    private function invoiceIssued(string $number, int $clientId, string $status, string $issued): void
    {
        $this->repo->save(new Invoice(
            organizationId: 1,
            clientId: $clientId,
            status: InvoiceStatus::from($status),
            subtotalCents: 100000,
            taxCents: 0,
            totalCents: 100000,
            invoiceNumber: $number,
            issuedAt: $issued,
            dueAt: '2026-12-31',
        ));
    }
}
