<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization;

use NeneInvoice\Auth\Role;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCase;
use NeneInvoice\Organization\DeleteOrganizationUseCase;
use NeneInvoice\Organization\GetOrganizationByIdUseCase;
use NeneInvoice\Organization\ListOrganizationsUseCase;
use NeneInvoice\Organization\OrganizationNotFoundException;
use NeneInvoice\Organization\OrganizationSlugConflictException;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryInitialAdminRepository;
use NeneInvoice\Tests\Support\InMemoryOrganizationRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use NeneInvoice\User\UserEmailConflictException;
use PHPUnit\Framework\TestCase;

final class OrganizationUseCasesTest extends TestCase
{
    private InMemoryOrganizationRepository $repo;
    private RecordingAuditRecorder $audit;
    private InMemoryInitialAdminRepository $admins;

    protected function setUp(): void
    {
        $this->repo = new InMemoryOrganizationRepository();
        $this->audit = new RecordingAuditRecorder();
        $this->admins = new InMemoryInitialAdminRepository();
    }

    private function createUseCase(): CreateOrganizationUseCase
    {
        return new CreateOrganizationUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->repo,
            fn () => $this->audit,
            fn () => $this->admins,
        );
    }

    public function test_create_persists_returns_with_id_and_audits(): void
    {
        $org = $this->createUseCase()->execute(1, new CreateOrganizationInput('Acme', 'acme'));

        self::assertNotNull($org->id);
        self::assertSame('Acme', $org->name);
        self::assertTrue($org->isActive);

        self::assertCount(1, $this->audit->records);
        self::assertSame('organization.created', $this->audit->records[0]['action']);
        self::assertSame(1, $this->audit->records[0]['actor_user_id']);
        self::assertNull($this->audit->records[0]['before']);

        // No admin fields → org only, no user provisioned.
        self::assertCount(0, $this->admins->created);
    }

    public function test_create_with_initial_admin_provisions_in_new_org_and_audits(): void
    {
        $org = $this->createUseCase()->execute(
            42,
            new CreateOrganizationInput('Beta KK', 'beta', 'free', 'owner@beta.example', 'correct horse battery'),
        );

        self::assertNotNull($org->id);

        // The admin lands in the freshly created org — never the caller's org.
        self::assertCount(1, $this->admins->created);
        $admin = $this->admins->created[0];
        self::assertSame($org->id, $admin->organizationId);
        self::assertSame(Role::Admin, $admin->role);
        self::assertSame('active', $admin->status);
        self::assertTrue(password_verify('correct horse battery', $admin->passwordHash));

        // Two audit entries, both scoped to the new org, in order.
        self::assertCount(2, $this->audit->records);
        self::assertSame('organization.created', $this->audit->records[0]['action']);
        self::assertSame('user.created', $this->audit->records[1]['action']);
        self::assertSame($org->id, $this->audit->records[1]['organization_id']);
        self::assertSame(42, $this->audit->records[1]['actor_user_id']);
        // The audit snapshot must not leak the password hash.
        self::assertIsArray($this->audit->records[1]['after']);
        self::assertArrayNotHasKey('password_hash', $this->audit->records[1]['after']);
        self::assertSame('admin', $this->audit->records[1]['after']['role'] ?? null);
    }

    public function test_create_with_duplicate_admin_email_throws_conflict(): void
    {
        $useCase = $this->createUseCase();
        $useCase->execute(1, new CreateOrganizationInput('One', 'one', 'free', 'dup@example.com', 'password-1234'));

        $this->expectException(UserEmailConflictException::class);
        $useCase->execute(1, new CreateOrganizationInput('Two', 'two', 'free', 'dup@example.com', 'password-1234'));
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $useCase = $this->createUseCase();
        $useCase->execute(1, new CreateOrganizationInput('First', 'dup'));

        $this->expectException(OrganizationSlugConflictException::class);
        $useCase->execute(1, new CreateOrganizationInput('Second', 'dup'));
    }

    public function test_list_returns_items_and_total(): void
    {
        $create = $this->createUseCase();
        $create->execute(1, new CreateOrganizationInput('A', 'a'));
        $create->execute(1, new CreateOrganizationInput('B', 'b'));

        $result = (new ListOrganizationsUseCase($this->repo))->execute(10, 0);

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
    }

    public function test_get_returns_organization_or_throws(): void
    {
        $id = $this->createUseCase()->execute(1, new CreateOrganizationInput('A', 'a'))->id;
        self::assertNotNull($id);

        $get = new GetOrganizationByIdUseCase($this->repo);
        self::assertSame('a', $get->execute($id)->slug);

        $this->expectException(OrganizationNotFoundException::class);
        $get->execute(999);
    }

    public function test_delete_removes_audits_and_throws_when_missing(): void
    {
        $id = $this->createUseCase()->execute(1, new CreateOrganizationInput('A', 'a'))->id;
        self::assertNotNull($id);

        $delete = new DeleteOrganizationUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, fn () => $this->audit);
        $delete->execute(1, $id);
        self::assertSame(0, $this->repo->count());
        self::assertSame('organization.deleted', $this->audit->records[1]['action']);
        self::assertNull($this->audit->records[1]['after']);

        $this->expectException(OrganizationNotFoundException::class);
        $delete->execute(1, $id);
    }
}
