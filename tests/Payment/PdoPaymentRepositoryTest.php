<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Payment\PdoPaymentRepository;
use PHPUnit\Framework\TestCase;

final class PdoPaymentRepositoryTest extends TestCase
{
    private PdoPaymentRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/payments.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->repository = new PdoPaymentRepository(new PdoDatabaseQueryExecutor($factory, $pdo));
    }

    public function test_saves_and_reads_back_payments_for_invoice(): void
    {
        $id = $this->repository->save(new Payment(
            organizationId: 1,
            invoiceId: 42,
            amountCents: 1000,
            paidAt: '2026-05-29 10:00:00',
            method: 'bank_transfer',
            note: 'first',
        ));

        $payments = $this->repository->findByInvoice(42);
        self::assertCount(1, $payments);
        self::assertSame($id, $payments[0]->id);
        self::assertSame(1000, $payments[0]->amountCents);
        self::assertSame('bank_transfer', $payments[0]->method);
        self::assertSame('first', $payments[0]->note);
    }

    public function test_total_paid_sums_only_matching_invoice(): void
    {
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 42, amountCents: 1000, paidAt: '2026-05-29 10:00:00'));
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 42, amountCents: 1200, paidAt: '2026-05-29 11:00:00'));
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 99, amountCents: 500, paidAt: '2026-05-29 12:00:00'));

        self::assertSame(2200, $this->repository->totalPaidForInvoice(42));
        self::assertSame(500, $this->repository->totalPaidForInvoice(99));
        self::assertSame(0, $this->repository->totalPaidForInvoice(7));
    }

    public function test_payments_ordered_by_paid_at(): void
    {
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 42, amountCents: 1200, paidAt: '2026-05-29 11:00:00'));
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 42, amountCents: 1000, paidAt: '2026-05-29 10:00:00'));

        $payments = $this->repository->findByInvoice(42);
        self::assertSame('2026-05-29 10:00:00', $payments[0]->paidAt);
        self::assertSame('2026-05-29 11:00:00', $payments[1]->paidAt);
    }
}
