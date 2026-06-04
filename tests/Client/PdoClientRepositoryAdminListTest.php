<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Client;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientListFilter;
use NeneInvoice\Client\ClientSort;
use NeneInvoice\Client\PdoClientRepository;
use PHPUnit\Framework\TestCase;

/** Real-DB coverage for the admin client list query (search + sort). */
final class PdoClientRepositoryAdminListTest extends TestCase
{
    private PdoClientRepository $repo;
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

        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/schema/clients.sql');
        self::assertIsString($sql);
        $pdo->exec($sql);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new PdoClientRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
    }

    private function client(string $name, ?string $contact, ?string $email, ?string $reg): void
    {
        $this->repo->save(new Client(
            organizationId: 1,
            name: $name,
            contactName: $contact,
            email: $email,
            registrationNumber: $reg,
        ));
    }

    public function test_searches_across_name_contact_email_and_registration(): void
    {
        $this->client('株式会社アルファ', '田中 一郎', 'tanaka@alpha.example', 'T1234567890123');
        $this->client('合同会社ベータ', '佐藤 花子', 'sato@beta.example', null);

        self::assertSame(1, $this->repo->countForAdminList(new ClientListFilter('アルファ')));
        self::assertSame(1, $this->repo->countForAdminList(new ClientListFilter('田中')));
        self::assertSame(1, $this->repo->countForAdminList(new ClientListFilter('sato@beta')));
        self::assertSame(1, $this->repo->countForAdminList(new ClientListFilter('T1234567890123')));
        self::assertSame(0, $this->repo->countForAdminList(new ClientListFilter('該当なし')));
    }

    public function test_sorts_by_name_ascending_and_descending(): void
    {
        $this->client('ガンマ', null, null, null);
        $this->client('アルファ', null, null, null);
        $this->client('ベータ', null, null, null);

        $asc = $this->repo->findForAdminList(new ClientListFilter(), new ClientSort('name', false), 20, 0);
        self::assertSame(['アルファ', 'ガンマ', 'ベータ'], array_map(static fn (Client $c): string => $c->name, $asc));

        $desc = $this->repo->findForAdminList(new ClientListFilter(), new ClientSort('name', true), 20, 0);
        self::assertSame(['ベータ', 'ガンマ', 'アルファ'], array_map(static fn (Client $c): string => $c->name, $desc));
    }
}
