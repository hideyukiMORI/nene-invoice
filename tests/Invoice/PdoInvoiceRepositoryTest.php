<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\PdoInvoiceRepository;
use PHPUnit\Framework\TestCase;

final class PdoInvoiceRepositoryTest extends TestCase
{
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

        $this->repository = new PdoInvoiceRepository(new PdoDatabaseQueryExecutor($factory, $pdo));
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

        self::assertSame(2, $this->repository->countByOrganization(1));
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

    public function test_list_and_count_scoped_to_organization(): void
    {
        $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));
        $this->repository->save(new Invoice(organizationId: 2, clientId: 9, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));

        self::assertSame(1, $this->repository->countByOrganization(1));
        self::assertCount(1, $this->repository->findAllByOrganization(1, 10, 0));
    }

    public function test_soft_delete_and_unknown_delete_throws(): void
    {
        $id = $this->repository->save(new Invoice(organizationId: 1, clientId: 5, status: InvoiceStatus::Draft, subtotalCents: 0, taxCents: 0, totalCents: 0));
        $this->repository->delete($id);
        self::assertNull($this->repository->findById($id));

        $this->expectException(InvoiceNotFoundException::class);
        $this->repository->delete(999);
    }
}
