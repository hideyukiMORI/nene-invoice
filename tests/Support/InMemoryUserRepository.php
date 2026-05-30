<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;
use NeneInvoice\User\UserNotFoundException;
use NeneInvoice\User\UserRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Identity lookups
 * ({@see findById()}, {@see findByEmail()}) are global, mirroring the
 * holder-less login / current-user paths. Org-scoped operations read the
 * request-scoped holder (defaulting to organization 1). {@see save()} keeps the
 * entity's organization so cross-org fixtures can be seeded while reads prove
 * isolation.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function findById(int $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findInOrganization(int $id): ?User
    {
        $user = $this->byId[$id] ?? null;

        return $user !== null && $user->organizationId === $this->orgId->get() ? $user : null;
    }

    /** @return list<User> */
    public function findAll(int $limit, int $offset): array
    {
        $organizationId = $this->orgId->get();
        $matches = array_values(array_filter(
            $this->byId,
            static fn (User $u): bool => $u->organizationId === $organizationId,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function count(): int
    {
        $organizationId = $this->orgId->get();

        return count(array_filter(
            $this->byId,
            static fn (User $u): bool => $u->organizationId === $organizationId,
        ));
    }

    public function save(User $user): int
    {
        if ($this->findByEmail($user->email) !== null) {
            throw new UserEmailConflictException($user->email);
        }

        $id = $this->nextId++;
        $now = '2026-05-29 00:00:00';

        $this->byId[$id] = new User(
            email: $user->email,
            passwordHash: $user->passwordHash,
            role: $user->role,
            organizationId: $user->organizationId,
            status: $user->status,
            id: $id,
            createdAt: $now,
            updatedAt: $now,
        );

        return $id;
    }

    public function update(User $user): void
    {
        if ($user->id === null || $this->findInOrganization($user->id) === null) {
            throw new UserNotFoundException($user->id ?? 0);
        }

        $this->byId[$user->id] = $user;
    }

    public function delete(int $id): void
    {
        if ($this->findInOrganization($id) === null) {
            throw new UserNotFoundException($id);
        }

        unset($this->byId[$id]);
    }
}
