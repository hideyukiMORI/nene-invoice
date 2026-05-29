<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Organization\Organization;
use NeneInvoice\Organization\OrganizationNotFoundException;
use NeneInvoice\Organization\OrganizationSlugConflictException;
use NeneInvoice\Organization\PdoOrganizationRepository;
use PHPUnit\Framework\TestCase;

final class PdoOrganizationRepositoryTest extends TestCase
{
    private PdoOrganizationRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/organizations.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->repository = new PdoOrganizationRepository(
            new PdoDatabaseQueryExecutor($factory, $pdo),
        );
    }

    public function test_finds_organization_by_id_and_slug_after_save(): void
    {
        $id = $this->repository->save(new Organization(
            name: 'Acme Foods',
            slug: 'acme-foods',
            plan: 'free',
            isActive: true,
        ));

        self::assertGreaterThan(0, $id);

        $byId = $this->repository->findById($id);
        self::assertNotNull($byId);
        self::assertSame('Acme Foods', $byId->name);
        self::assertSame('acme-foods', $byId->slug);
        self::assertTrue($byId->isActive);
        self::assertNotNull($byId->createdAt);

        $bySlug = $this->repository->findBySlug('acme-foods');
        self::assertNotNull($bySlug);
        self::assertSame($id, $bySlug->id);
    }

    public function test_returns_null_when_organization_not_found(): void
    {
        self::assertNull($this->repository->findById(999));
        self::assertNull($this->repository->findBySlug('missing'));
    }

    public function test_counts_and_lists_organizations(): void
    {
        $this->repository->save(new Organization(name: 'Org A', slug: 'org-a', plan: 'free', isActive: true));
        $this->repository->save(new Organization(name: 'Org B', slug: 'org-b', plan: 'free', isActive: true));

        self::assertSame(2, $this->repository->count());

        $all = $this->repository->findAll(10, 0);
        self::assertCount(2, $all);
        self::assertSame('org-a', $all[0]->slug);
    }

    public function test_rejects_duplicate_slug_when_saving(): void
    {
        $this->repository->save(new Organization(name: 'First', slug: 'dup', plan: 'free', isActive: true));

        $this->expectException(OrganizationSlugConflictException::class);
        $this->repository->save(new Organization(name: 'Second', slug: 'dup', plan: 'free', isActive: true));
    }

    public function test_updates_existing_organization(): void
    {
        $id = $this->repository->save(new Organization(name: 'Before', slug: 'org', plan: 'free', isActive: true));

        $this->repository->update(new Organization(
            name: 'After',
            slug: 'org',
            plan: 'pro',
            isActive: false,
            id: $id,
        ));

        $updated = $this->repository->findById($id);
        self::assertNotNull($updated);
        self::assertSame('After', $updated->name);
        self::assertSame('pro', $updated->plan);
        self::assertFalse($updated->isActive);
    }

    public function test_throws_when_deleting_unknown_organization(): void
    {
        $this->expectException(OrganizationNotFoundException::class);
        $this->repository->delete(424242);
    }

    public function test_deletes_existing_organization(): void
    {
        $id = $this->repository->save(new Organization(name: 'Temp', slug: 'temp', plan: 'free', isActive: true));

        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->count());
    }
}
