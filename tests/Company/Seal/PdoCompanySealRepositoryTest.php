<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Company\Seal;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Company\Seal\PdoCompanySealRepository;
use PHPUnit\Framework\TestCase;

final class PdoCompanySealRepositoryTest extends TestCase
{
    private PdoCompanySealRepository $repo;
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

        $schema = file_get_contents(dirname(__DIR__, 3) . '/database/schema/company_seal_images.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new PdoCompanySealRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
    }

    public function test_find_returns_null_and_exists_false_when_unset(): void
    {
        self::assertNull($this->repo->find());
        self::assertFalse($this->repo->exists());
    }

    public function test_save_inserts_then_updates(): void
    {
        $this->repo->save('AAAA');
        self::assertSame('AAAA', $this->repo->find());
        self::assertTrue($this->repo->exists());

        $this->repo->save('BBBB');
        self::assertSame('BBBB', $this->repo->find());
    }

    public function test_delete_removes_the_seal(): void
    {
        $this->repo->save('AAAA');
        $this->repo->delete();

        self::assertNull($this->repo->find());
        self::assertFalse($this->repo->exists());
    }

    public function test_orgs_are_isolated(): void
    {
        $this->holder->set(1);
        $this->repo->save('ORG1SEAL');
        $this->holder->set(2);
        self::assertNull($this->repo->find());

        $this->holder->set(1);
        self::assertSame('ORG1SEAL', $this->repo->find());
    }
}
