<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Template;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Template\PdoTemplateRepository;
use NeneInvoice\Template\Template;
use NeneInvoice\Template\TemplateNotFoundException;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PdoTemplateRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private PdoTemplateRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/templates.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoTemplateRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->orgId, new FixedClock());
    }

    public function test_save_then_find_round_trips(): void
    {
        $id = $this->repository->save(new Template(organizationId: 1, name: '月次保守', notes: '毎月'));

        $template = $this->repository->findById($id);
        self::assertNotNull($template);
        self::assertSame('月次保守', $template->name);
        self::assertSame('毎月', $template->notes);
    }

    public function test_reads_are_scoped_to_the_request_org(): void
    {
        $this->repository->save(new Template(organizationId: 1, name: 'Mine'));

        $this->orgId->set(2);
        self::assertSame(0, $this->repository->count());
        self::assertCount(0, $this->repository->findAll(50, 0));
    }

    public function test_update_changes_fields(): void
    {
        $id = $this->repository->save(new Template(organizationId: 1, name: 'Before', notes: null));

        $existing = $this->repository->findById($id);
        self::assertNotNull($existing);
        $this->repository->update(new Template(
            organizationId: 1,
            name: 'After',
            notes: 'now with notes',
            id: $id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        ));

        $updated = $this->repository->findById($id);
        self::assertNotNull($updated);
        self::assertSame('After', $updated->name);
        self::assertSame('now with notes', $updated->notes);
    }

    public function test_delete_is_soft_and_hides_the_row(): void
    {
        $id = $this->repository->save(new Template(organizationId: 1, name: 'Doomed'));

        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->count());
    }

    public function test_delete_missing_throws(): void
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->repository->delete(999);
    }
}
