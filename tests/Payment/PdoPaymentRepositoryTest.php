<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Payment\PdoPaymentRepository;
use PHPUnit\Framework\TestCase;

final class PdoPaymentRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private PdoPaymentRepository $repository;
    private \PDO $pdo;

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

        foreach (['payments', 'invoices'] as $table) {
            $schema = file_get_contents(dirname(__DIR__, 2) . "/database/schema/{$table}.sql");
            self::assertIsString($schema);
            $pdo->exec($schema);
        }

        $this->pdo   = $pdo;
        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoPaymentRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->orgId);
    }

    /** Inserts an invoice row directly (the payment repo cannot create invoices). */
    private function insertInvoice(
        int $id,
        int $organizationId,
        string $status,
        int $totalCents,
        ?string $dueAt,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO invoices (id, organization_id, client_id, status, is_qualified_invoice, due_at, subtotal_cents, tax_cents, total_cents, is_deleted, created_at, updated_at)
             VALUES (?, ?, 1, ?, 0, ?, ?, 0, ?, 0, ?, ?)',
        );
        $now = '2026-05-29 00:00:00';
        $stmt->execute([$id, $organizationId, $status, $dueAt, $totalCents, $totalCents, $now, $now]);
    }

    /** Inserts a payment row directly with an explicit organization (the repo forces org from the holder). */
    private function insertPayment(int $organizationId, int $invoiceId, int $amountCents, string $paidAt, bool $isDeleted): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payments (organization_id, invoice_id, amount_cents, paid_at, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $now = '2026-05-29 00:00:00';
        $stmt->execute([$organizationId, $invoiceId, $amountCents, $paidAt, $isDeleted ? 1 : 0, $now, $now]);
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

    public function test_sum_paid_for_invoices_batches_and_omits_empties(): void
    {
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 42, amountCents: 1000, paidAt: '2026-05-29 10:00:00'));
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 42, amountCents: 1200, paidAt: '2026-05-29 11:00:00'));
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 99, amountCents: 500, paidAt: '2026-05-29 12:00:00'));

        $totals = $this->repository->sumPaidForInvoices([42, 99, 7]);

        self::assertSame(2200, $totals[42] ?? null);
        self::assertSame(500, $totals[99] ?? null);
        self::assertArrayNotHasKey(7, $totals); // no payments → omitted
    }

    public function test_sum_paid_for_invoices_empty_input(): void
    {
        self::assertSame([], $this->repository->sumPaidForInvoices([]));
    }

    public function test_stores_and_finds_by_external_reference_and_idempotency_key(): void
    {
        $id = $this->repository->save(new Payment(
            organizationId: 1,
            invoiceId: 42,
            amountCents: 1000,
            paidAt: '2026-05-29 10:00:00',
            externalReference: 'clear:recon:777',
            idempotencyKey: 'clear:recon:777:v1',
        ));

        $byKey = $this->repository->findByIdempotencyKey('clear:recon:777:v1');
        self::assertNotNull($byKey);
        self::assertSame($id, $byKey->id);
        self::assertSame('clear:recon:777', $byKey->externalReference);

        self::assertNull($this->repository->findByIdempotencyKey('unknown'));

        $this->orgId->set(2);
        self::assertNull($this->repository->findByIdempotencyKey('clear:recon:777:v1')); // other org
    }

    public function test_void_excludes_from_totals_and_is_idempotent(): void
    {
        $id = $this->repository->save(new Payment(organizationId: 1, invoiceId: 42, amountCents: 1000, paidAt: '2026-05-29 10:00:00'));
        self::assertSame(1000, $this->repository->totalPaidForInvoice(42));

        $this->repository->markVoided($id);
        self::assertSame(0, $this->repository->totalPaidForInvoice(42));
        self::assertCount(0, $this->repository->findByInvoice(42));

        // idempotent: voiding again is a no-op
        $this->repository->markVoided($id);
        self::assertSame(0, $this->repository->totalPaidForInvoice(42));

        $voided = $this->repository->findById($id);
        self::assertNotNull($voided);
        self::assertTrue($voided->isDeleted);
    }

    public function test_received_total_between_sums_non_void_in_range_and_scopes_org(): void
    {
        // in range
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 1, amountCents: 1000, paidAt: '2026-05-10 09:00:00'));
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 2, amountCents: 2000, paidAt: '2026-05-31 23:59:59'));
        // boundary: end is exclusive
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 3, amountCents: 4000, paidAt: '2026-06-01 00:00:00'));
        // before range
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 4, amountCents: 8000, paidAt: '2026-04-30 23:59:59'));
        // other org (raw insert: the repo forces organization_id from the holder)
        $this->insertPayment(2, 5, 9000, '2026-05-15 09:00:00', false);
        // voided in range
        $voidId = $this->repository->save(new Payment(organizationId: 1, invoiceId: 6, amountCents: 500, paidAt: '2026-05-20 09:00:00'));
        $this->repository->markVoided($voidId);

        $total = $this->repository->receivedTotalBetween('2026-05-01 00:00:00', '2026-06-01 00:00:00');

        self::assertSame(3000, $total);
    }

    public function test_aging_buckets_split_outstanding_by_overdue_age(): void
    {
        // now reference for the assertion: 2026-05-31, thirtyDaysAgo: 2026-05-01
        $now          = '2026-05-31 12:00:00';
        $thirtyDaysAgo = '2026-05-01 12:00:00';

        // current: not yet due
        $this->insertInvoice(1, 1, 'issued', 10000, '2026-06-30 00:00:00');
        // current: no due date
        $this->insertInvoice(2, 1, 'issued', 5000, null);
        // 1–30 days overdue (due 2026-05-20, partially paid 2000 → net 6000)
        $this->insertInvoice(3, 1, 'partially_paid', 8000, '2026-05-20 00:00:00');
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 3, amountCents: 2000, paidAt: '2026-05-21 09:00:00'));
        // 31+ days overdue
        $this->insertInvoice(4, 1, 'issued', 7000, '2026-03-31 00:00:00');
        // excluded: paid invoice
        $this->insertInvoice(5, 1, 'paid', 9999, '2026-03-01 00:00:00');
        // excluded: fully covered net = 0
        $this->insertInvoice(6, 1, 'issued', 3000, '2026-04-01 00:00:00');
        $this->repository->save(new Payment(organizationId: 1, invoiceId: 6, amountCents: 3000, paidAt: '2026-04-02 09:00:00'));
        // excluded: other org
        $this->insertInvoice(7, 2, 'issued', 12000, '2026-03-01 00:00:00');

        $aging = $this->repository->agingBuckets($now, $thirtyDaysAgo);

        self::assertSame(15000, $aging['current']);          // 10000 + 5000
        self::assertSame(6000, $aging['overdue_1_30']);      // 8000 - 2000
        self::assertSame(7000, $aging['overdue_31_plus']);
    }
}
