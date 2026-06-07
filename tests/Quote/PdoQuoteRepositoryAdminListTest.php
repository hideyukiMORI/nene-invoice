<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Quote\PdoQuoteRepository;
use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteListFilter;
use NeneInvoice\Quote\QuoteSort;
use NeneInvoice\Quote\QuoteStatus;
use PDO;
use PHPUnit\Framework\TestCase;

/** Real-DB coverage for the admin quote list query (joins clients). */
final class PdoQuoteRepositoryAdminListTest extends TestCase
{
    private PdoQuoteRepository $repo;
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

        foreach (['clients', 'quotes'] as $table) {
            $sql = file_get_contents(dirname(__DIR__, 2) . "/database/schema/{$table}.sql");
            self::assertIsString($sql);
            $pdo->exec($sql);
        }

        $this->pdo    = $pdo;
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new PdoQuoteRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
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

    private function quote(string $number, int $clientId, int $total, string $status, string $validUntil): void
    {
        $this->repo->save(new Quote(
            organizationId: 1,
            clientId: $clientId,
            quoteNumber: $number,
            status: QuoteStatus::from($status),
            subtotalCents: $total,
            taxCents: 0,
            totalCents: $total,
            issuedAt: '2026-05-01',
            validUntil: $validUntil,
        ));
    }

    public function test_searches_by_number_or_client_name_and_returns_client_name(): void
    {
        $this->client(1, 'アルファ商事');
        $this->client(2, 'ベータ工業');
        $this->quote('EST-001', 1, 100000, 'sent', '2026-06-30');
        $this->quote('EST-002', 2, 200000, 'accepted', '2026-07-31');

        $byName = $this->repo->findForAdminList(new QuoteListFilter(search: 'アルファ'), new QuoteSort(), 20, 0);
        self::assertCount(1, $byName);
        self::assertSame('EST-001', $byName[0]->quote->quoteNumber);
        self::assertSame('アルファ商事', $byName[0]->clientName);

        $byNumber = $this->repo->findForAdminList(new QuoteListFilter(search: 'EST-002'), new QuoteSort(), 20, 0);
        self::assertCount(1, $byNumber);
        self::assertSame('ベータ工業', $byNumber[0]->clientName);
    }

    public function test_filters_by_status_amount_and_valid_until_range(): void
    {
        $this->client(1, 'A');
        $this->quote('EST-001', 1, 100000, 'sent', '2026-06-30');
        $this->quote('EST-002', 1, 500000, 'accepted', '2026-07-31');

        self::assertSame(1, $this->repo->countForAdminList(new QuoteListFilter(statuses: ['accepted'])));
        self::assertSame(1, $this->repo->countForAdminList(new QuoteListFilter(totalMin: 200000)));
        self::assertSame(1, $this->repo->countForAdminList(new QuoteListFilter(totalMax: 200000)));
        self::assertSame(1, $this->repo->countForAdminList(new QuoteListFilter(validFrom: '2026-07-01')));
        self::assertSame(1, $this->repo->countForAdminList(new QuoteListFilter(validTo: '2026-07-01')));
    }

    public function test_sorts_by_total_ascending_and_descending(): void
    {
        $this->client(1, 'A');
        $this->quote('EST-001', 1, 300000, 'sent', '2026-06-30');
        $this->quote('EST-002', 1, 100000, 'sent', '2026-07-31');
        $this->quote('EST-003', 1, 200000, 'sent', '2026-08-31');

        $asc = $this->repo->findForAdminList(new QuoteListFilter(), new QuoteSort('total', false), 20, 0);
        self::assertSame([100000, 200000, 300000], array_map(static fn ($r): int => $r->quote->totalCents, $asc));

        $desc = $this->repo->findForAdminList(new QuoteListFilter(), new QuoteSort('total', true), 20, 0);
        self::assertSame([300000, 200000, 100000], array_map(static fn ($r): int => $r->quote->totalCents, $desc));
    }

    public function test_export_reflects_filter_including_drafts(): void
    {
        $this->client(1, 'アルファ商事');
        $this->quote('EST-SENT', 1, 100000, 'sent', '2026-06-30');
        $this->quote('EST-DRAFT', 1, 200000, 'draft', '2026-07-31');

        // No status filter: the export mirrors the list, so drafts are included.
        $all = $this->repo->findForExport(new QuoteListFilter());
        self::assertCount(2, $all);

        // A status filter narrows the export the same way it narrows the list.
        $accepted = $this->repo->findForExport(new QuoteListFilter(statuses: ['draft']));
        self::assertCount(1, $accepted);
        self::assertSame('EST-DRAFT', $accepted[0]['quote_number']);
        self::assertSame('アルファ商事', $accepted[0]['client_name']);
    }
}
