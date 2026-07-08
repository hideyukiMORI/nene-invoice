<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\RecurringInvoice\PdoRecurringInvoiceRepository;
use NeneInvoice\RecurringInvoice\RecurringFrequency;
use NeneInvoice\RecurringInvoice\RecurringInvoice;
use NeneInvoice\RecurringInvoice\RecurringInvoiceNotFoundException;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PdoRecurringInvoiceRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private PdoRecurringInvoiceRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/recurring_invoices.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new PdoRecurringInvoiceRepository(
            new PdoDatabaseQueryExecutor($factory, $pdo),
            $this->holder,
            new FixedClock(),
        );
    }

    private function schedule(
        string $name = '月次顧問料',
        string $nextRunOn = '2026-07-01',
        bool $isActive = true,
        RecurringFrequency $frequency = RecurringFrequency::Monthly,
    ): RecurringInvoice {
        return new RecurringInvoice(
            organizationId: 1,
            clientId: 5,
            name: $name,
            frequency: $frequency,
            subtotalCents: 50000,
            taxCents: 5000,
            totalCents: 55000,
            nextRunOn: $nextRunOn,
            isActive: $isActive,
            notes: 'ご利用ありがとうございます。',
        );
    }

    public function test_save_and_find_round_trips_all_fields(): void
    {
        $id = $this->repository->save($this->schedule(frequency: RecurringFrequency::Quarterly));

        $found = $this->repository->findById($id);
        self::assertNotNull($found);
        self::assertSame($id, $found->id);
        self::assertSame('月次顧問料', $found->name);
        self::assertSame(RecurringFrequency::Quarterly, $found->frequency);
        self::assertSame(55000, $found->totalCents);
        self::assertSame('2026-07-01', $found->nextRunOn);
        self::assertNull($found->lastRunOn);
        self::assertTrue($found->isActive);
        self::assertSame('ご利用ありがとうございます。', $found->notes);
    }

    public function test_reads_are_scoped_to_the_organization(): void
    {
        $id = $this->repository->save($this->schedule());

        $this->holder->set(2);
        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->countByOrganization());
    }

    public function test_find_by_organization_lists_newest_first_with_paging(): void
    {
        $this->repository->save($this->schedule(name: 'A'));
        $this->repository->save($this->schedule(name: 'B'));
        $this->repository->save($this->schedule(name: 'C'));

        self::assertSame(3, $this->repository->countByOrganization());

        $page = $this->repository->findByOrganization(2, 0);
        self::assertCount(2, $page);
        self::assertSame('C', $page[0]->name); // newest (highest id) first
        self::assertSame('B', $page[1]->name);

        $rest = $this->repository->findByOrganization(2, 2);
        self::assertCount(1, $rest);
        self::assertSame('A', $rest[0]->name);
    }

    public function test_find_due_returns_active_schedules_on_or_before_date_oldest_first(): void
    {
        $this->repository->save($this->schedule(name: 'past', nextRunOn: '2026-06-15'));
        $this->repository->save($this->schedule(name: 'boundary', nextRunOn: '2026-07-01'));
        $this->repository->save($this->schedule(name: 'future', nextRunOn: '2026-07-02'));
        $this->repository->save($this->schedule(name: 'inactive-due', nextRunOn: '2026-06-01', isActive: false));

        $due = $this->repository->findDue('2026-07-01');

        // boundary (== date) is inclusive; future excluded; inactive excluded.
        self::assertSame(['past', 'boundary'], array_map(static fn ($s) => $s->name, $due));
    }

    public function test_find_due_excludes_the_day_after(): void
    {
        $this->repository->save($this->schedule(nextRunOn: '2026-07-02'));

        self::assertSame([], $this->repository->findDue('2026-07-01'));
    }

    public function test_update_persists_changes_and_advances_run_dates(): void
    {
        $id = $this->repository->save($this->schedule());
        $current = $this->repository->findById($id);
        self::assertNotNull($current);

        $this->repository->update(new RecurringInvoice(
            organizationId: $current->organizationId,
            clientId: $current->clientId,
            name: '改定後 顧問料',
            frequency: $current->frequency,
            subtotalCents: 60000,
            taxCents: 6000,
            totalCents: 66000,
            nextRunOn: '2026-08-01',
            lastRunOn: '2026-07-01',
            isActive: false,
            notes: $current->notes,
            id: $current->id,
        ));

        $updated = $this->repository->findById($id);
        self::assertNotNull($updated);
        self::assertSame('改定後 顧問料', $updated->name);
        self::assertSame(66000, $updated->totalCents);
        self::assertSame('2026-08-01', $updated->nextRunOn);
        self::assertSame('2026-07-01', $updated->lastRunOn);
        self::assertFalse($updated->isActive);
    }

    public function test_soft_delete_hides_the_schedule(): void
    {
        $id = $this->repository->save($this->schedule());

        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->countByOrganization());
    }

    public function test_update_unknown_id_throws(): void
    {
        $this->expectException(RecurringInvoiceNotFoundException::class);
        $this->repository->update($this->schedule()); // id null
    }

    public function test_delete_unknown_id_throws(): void
    {
        $this->expectException(RecurringInvoiceNotFoundException::class);
        $this->repository->delete(999);
    }
}
