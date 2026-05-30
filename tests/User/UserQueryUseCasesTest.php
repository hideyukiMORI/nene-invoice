<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\User;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Auth\Role;
use NeneInvoice\Tests\Support\InMemoryUserRepository;
use NeneInvoice\User\GetUserByIdUseCase;
use NeneInvoice\User\ListUsersUseCase;
use NeneInvoice\User\User;
use NeneInvoice\User\UserNotFoundException;
use PHPUnit\Framework\TestCase;

final class UserQueryUseCasesTest extends TestCase
{
    private InMemoryUserRepository $repo;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private int $org1User;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new InMemoryUserRepository($this->holder);
        $this->org1User = $this->repo->save(new User('a@org1', 'h', Role::Admin, 1));
        $this->repo->save(new User('b@org1', 'h', Role::Member, 1));
        $this->repo->save(new User('c@org2', 'h', Role::Admin, 2));
    }

    public function test_list_is_scoped_to_organization(): void
    {
        $result = (new ListUsersUseCase($this->repo))->execute(10, 0);

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
        foreach ($result->items as $user) {
            self::assertSame(1, $user->organizationId);
        }
    }

    public function test_get_returns_user_in_same_organization(): void
    {
        $user = (new GetUserByIdUseCase($this->repo))->execute($this->org1User);

        self::assertSame('a@org1', $user->email);
    }

    public function test_get_hides_user_from_another_organization_as_not_found(): void
    {
        // org-2 user exists, but an org-1 admin must not see it.
        $org2UserId = $this->repo->findByEmail('c@org2')?->id;
        self::assertNotNull($org2UserId);

        $this->expectException(UserNotFoundException::class);
        (new GetUserByIdUseCase($this->repo))->execute($org2UserId);
    }
}
