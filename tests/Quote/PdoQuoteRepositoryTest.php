<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Quote\PdoQuoteRepository;
use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Quote\QuoteStatus;
use PHPUnit\Framework\TestCase;

final class PdoQuoteRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private PdoQuoteRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/quotes.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoQuoteRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->orgId);
    }

    private function draft(int $org, int $client, string $number): Quote
    {
        return new Quote(
            organizationId: $org,
            clientId: $client,
            quoteNumber: $number,
            status: QuoteStatus::Draft,
            subtotalCents: 3000,
            taxCents: 300,
            totalCents: 3300,
        );
    }

    public function test_saves_and_reads_back_with_totals(): void
    {
        $id = $this->repository->save($this->draft(1, 5, 'EST-2026-001'));

        $quote = $this->repository->findById($id);
        self::assertNotNull($quote);
        self::assertSame('EST-2026-001', $quote->quoteNumber);
        self::assertSame(QuoteStatus::Draft, $quote->status);
        self::assertSame(3300, $quote->totalCents);
        self::assertSame(5, $quote->clientId);
    }

    public function test_list_and_count_scoped_to_organization(): void
    {
        $org1QuoteId = $this->repository->save($this->draft(1, 5, 'EST-2026-001'));
        $this->repository->save($this->draft(1, 5, 'EST-2026-002'));

        $this->orgId->set(2);
        $this->repository->save($this->draft(2, 9, 'EST-2026-001'));

        $this->orgId->set(1);
        self::assertSame(2, $this->repository->count());
        self::assertCount(2, $this->repository->findAll(10, 0));

        $this->orgId->set(2);
        self::assertSame(1, $this->repository->count());
        // A caller in another org must not read the row even by direct id.
        self::assertNull($this->repository->findById($org1QuoteId));
    }

    public function test_update_changes_status_and_totals(): void
    {
        $id = $this->repository->save($this->draft(1, 5, 'EST-2026-001'));
        $quote = $this->repository->findById($id);
        self::assertNotNull($quote);

        $this->repository->update(new Quote(
            organizationId: 1,
            clientId: 5,
            quoteNumber: 'EST-2026-001',
            status: QuoteStatus::Sent,
            subtotalCents: 5000,
            taxCents: 500,
            totalCents: 5500,
            id: $id,
        ));

        $updated = $this->repository->findById($id);
        self::assertNotNull($updated);
        self::assertSame(QuoteStatus::Sent, $updated->status);
        self::assertSame(5500, $updated->totalCents);
    }

    public function test_soft_delete_hides_quote(): void
    {
        $id = $this->repository->save($this->draft(1, 5, 'EST-2026-001'));

        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->count());
    }

    public function test_delete_throws_for_unknown_quote(): void
    {
        $this->expectException(QuoteNotFoundException::class);
        $this->repository->delete(999);
    }
}
