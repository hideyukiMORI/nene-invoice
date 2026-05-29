<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization;

use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCase;
use NeneInvoice\Organization\DeleteOrganizationUseCase;
use NeneInvoice\Organization\GetOrganizationByIdUseCase;
use NeneInvoice\Organization\ListOrganizationsUseCase;
use NeneInvoice\Organization\OrganizationNotFoundException;
use NeneInvoice\Organization\OrganizationSlugConflictException;
use NeneInvoice\Tests\Support\InMemoryOrganizationRepository;
use PHPUnit\Framework\TestCase;

final class OrganizationUseCasesTest extends TestCase
{
    private InMemoryOrganizationRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new InMemoryOrganizationRepository();
    }

    public function test_create_persists_and_returns_organization_with_id(): void
    {
        $useCase = new CreateOrganizationUseCase($this->repo);

        $org = $useCase->execute(new CreateOrganizationInput('Acme', 'acme'));

        self::assertNotNull($org->id);
        self::assertSame('Acme', $org->name);
        self::assertSame('acme', $org->slug);
        self::assertSame('free', $org->plan);
        self::assertTrue($org->isActive);
        self::assertNotNull($org->createdAt);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $useCase = new CreateOrganizationUseCase($this->repo);
        $useCase->execute(new CreateOrganizationInput('First', 'dup'));

        $this->expectException(OrganizationSlugConflictException::class);
        $useCase->execute(new CreateOrganizationInput('Second', 'dup'));
    }

    public function test_list_returns_items_and_total(): void
    {
        $create = new CreateOrganizationUseCase($this->repo);
        $create->execute(new CreateOrganizationInput('A', 'a'));
        $create->execute(new CreateOrganizationInput('B', 'b'));

        $result = (new ListOrganizationsUseCase($this->repo))->execute(10, 0);

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
    }

    public function test_get_returns_organization_or_throws(): void
    {
        $id = (new CreateOrganizationUseCase($this->repo))->execute(new CreateOrganizationInput('A', 'a'))->id;
        self::assertNotNull($id);

        $get = new GetOrganizationByIdUseCase($this->repo);
        self::assertSame('a', $get->execute($id)->slug);

        $this->expectException(OrganizationNotFoundException::class);
        $get->execute(999);
    }

    public function test_delete_removes_or_throws_when_missing(): void
    {
        $id = (new CreateOrganizationUseCase($this->repo))->execute(new CreateOrganizationInput('A', 'a'))->id;
        self::assertNotNull($id);

        $delete = new DeleteOrganizationUseCase($this->repo);
        $delete->execute($id);
        self::assertSame(0, $this->repo->count());

        $this->expectException(OrganizationNotFoundException::class);
        $delete->execute($id);
    }
}
