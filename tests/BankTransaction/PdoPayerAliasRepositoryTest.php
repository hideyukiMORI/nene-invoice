<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\PayerAlias;
use NeneInvoice\BankTransaction\PayerAliasNotFoundException;
use NeneInvoice\BankTransaction\PdoPayerAliasRepository;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PdoPayerAliasRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private PdoPayerAliasRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/payer_aliases.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new PdoPayerAliasRepository(
            new PdoDatabaseQueryExecutor($factory, $pdo),
            $this->holder,
            new FixedClock(),
        );
    }

    public function test_upsert_inserts_then_finds_by_normalized_name(): void
    {
        $id = $this->repository->upsert(new PayerAlias(organizationId: 1, normalizedName: 'ネネシヨウカイ', clientId: 7));

        $found = $this->repository->findByNormalizedName('ネネシヨウカイ');
        self::assertNotNull($found);
        self::assertSame($id, $found->id);
        self::assertSame(7, $found->clientId);
    }

    public function test_upsert_updates_existing_mapping_in_place(): void
    {
        $first  = $this->repository->upsert(new PayerAlias(organizationId: 1, normalizedName: 'ネネシヨウカイ', clientId: 7));
        $second = $this->repository->upsert(new PayerAlias(organizationId: 1, normalizedName: 'ネネシヨウカイ', clientId: 9));

        self::assertSame($first, $second); // same row
        self::assertSame(1, $this->repository->countByOrganization());
        $found = $this->repository->findByNormalizedName('ネネシヨウカイ');
        self::assertNotNull($found);
        self::assertSame(9, $found->clientId); // re-pointed
    }

    public function test_reads_are_scoped_to_the_organization(): void
    {
        $this->repository->upsert(new PayerAlias(organizationId: 1, normalizedName: 'ネネ', clientId: 7));

        $this->holder->set(2);
        self::assertNull($this->repository->findByNormalizedName('ネネ'));
        self::assertSame(0, $this->repository->countByOrganization());
        self::assertSame([], $this->repository->findByOrganization(10, 0));
    }

    public function test_list_and_count(): void
    {
        $this->repository->upsert(new PayerAlias(organizationId: 1, normalizedName: 'アアア', clientId: 1));
        $this->repository->upsert(new PayerAlias(organizationId: 1, normalizedName: 'イイイ', clientId: 2));

        self::assertSame(2, $this->repository->countByOrganization());
        self::assertCount(2, $this->repository->findByOrganization(10, 0));
    }

    public function test_delete_then_missing_throws(): void
    {
        $id = $this->repository->upsert(new PayerAlias(organizationId: 1, normalizedName: 'ネネ', clientId: 7));
        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        $this->expectException(PayerAliasNotFoundException::class);
        $this->repository->delete($id);
    }
}
