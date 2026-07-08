<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceListFilter;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\PdoInvoiceRepository;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PdoInvoiceRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private PdoInvoiceRepository $repository;

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
        $pdo = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/invoices.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoInvoiceRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->orgId, new FixedClock());
    }

    public function test_saves_draft_without_number_then_reads_back(): void
    {
        $id = $this->repository->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Draft,
            subtotalCents: 2000,
            taxCents: 180,
            totalCents: 2180,
            quoteId: 9,
        ));

        $invoice = $this->repository->findById($id);
        self::assertNotNull($invoice);
        self::assertNull($invoice->invoiceNumber);
        self::assertSame(InvoiceStatus::Draft, $invoice->status);
        self::assertSame(9, $invoice->quoteId);
        self::assertFalse($invoice->isQualifiedInvoice);
        self::assertSame(2180, $invoice->totalCents);
    }

    public function test_multiple_drafts_with_null_number_allowed(): void
    {
        $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));
        $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));

        self::assertSame(2, $this->repository->count());
    }

    public function test_issue_assigns_number_and_qualified_flag(): void
    {
        $id = $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Draft, subtotalCents: 1000, taxCents: 100, totalCents: 1100));

        $this->repository->update(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Issued,
            subtotalCents: 1000,
            taxCents: 100,
            totalCents: 1100,
            isQualifiedInvoice: true,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-29 00:00:00',
            id: $id,
        ));

        $issued = $this->repository->findById($id);
        self::assertNotNull($issued);
        self::assertSame('INV-2026-001', $issued->invoiceNumber);
        self::assertSame(InvoiceStatus::Issued, $issued->status);
        self::assertTrue($issued->isQualifiedInvoice);
    }

    public function test_list_count_and_find_by_id_scoped_to_organization(): void
    {
        $org1Id = $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));

        $this->orgId->set(2);
        $this->repository->save(new Invoice(organizationId: 2, clientId: 9, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));

        $this->orgId->set(1);
        self::assertSame(1, $this->repository->count());
        self::assertCount(1, $this->repository->findAll(10, 0));

        // A caller in another org must not read the row even by direct id.
        $this->orgId->set(2);
        self::assertNull($this->repository->findById($org1Id));
    }

    public function test_soft_delete_and_unknown_delete_throws(): void
    {
        $id = $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));
        $this->repository->delete($id);
        self::assertNull($this->repository->findById($id));

        $this->expectException(InvoiceNotFoundException::class);
        $this->repository->delete(999);
    }

    public function test_filter_by_status_client_due_and_outstanding(): void
    {
        // org 1: a paid one (client 5), an issued one past due (client 5), an issued one future (client 9)
        $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Paid, subtotalCents: 1000, taxCents: 0, totalCents: 1000, invoiceNumber: 'INV-1', issuedAt: '2026-01-01 00:00:00', dueAt: '2026-01-31'));
        $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Issued, subtotalCents: 2000, taxCents: 0, totalCents: 2000, invoiceNumber: 'INV-2', issuedAt: '2026-02-01 00:00:00', dueAt: '2026-02-28'));
        $this->repository->save(new Invoice(organizationId: 1, clientId: 9, status: InvoiceStatus::Issued, subtotalCents: 3000, taxCents: 0, totalCents: 3000, invoiceNumber: 'INV-3', issuedAt: '2026-02-01 00:00:00', dueAt: '2026-12-31'));

        // status filter
        $issued = new InvoiceListFilter(statuses: ['issued']);
        self::assertSame(2, $this->repository->countFiltered($issued));

        // client filter
        $client5 = new InvoiceListFilter(clientId: 5);
        self::assertSame(2, $this->repository->countFiltered($client5));

        // outstanding only (open = issued/partially_paid) excludes the paid one
        $open = new InvoiceListFilter(outstandingOnly: true);
        self::assertSame(2, $this->repository->countFiltered($open));

        // overdue as of 2026-06-01: INV-2 (due 02-28) is overdue, INV-3 (due 12-31) is not
        $overdue = new InvoiceListFilter(overdueOnly: true, today: '2026-06-01');
        $rows = $this->repository->findFiltered($overdue, 10, 0);
        self::assertCount(1, $rows);
        self::assertSame('INV-2', $rows[0]->invoiceNumber);

        // due_before
        $dueBeforeMarch = new InvoiceListFilter(dueBefore: '2026-03-01');
        self::assertSame(2, $this->repository->countFiltered($dueBeforeMarch)); // 01-31 and 02-28
    }
}
