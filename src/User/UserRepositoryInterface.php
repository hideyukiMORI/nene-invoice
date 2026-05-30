<?php

declare(strict_types=1);

namespace NeneInvoice\User;

/**
 * Persistence for users.
 *
 * Identity lookups ({@see findById()}, {@see findByEmail()}) are deliberately
 * NOT scoped to the org holder: they run on holder-less paths — login (pre-auth)
 * and "current user" resolution from the token `sub`. Every other operation is
 * scoped to the organization held in the request-scoped org holder (ADR 0006).
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /** Looks up a user by id within the resolved organization (holder-scoped). */
    public function findInOrganization(int $id): ?User;

    /** @return list<User> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /** @throws UserEmailConflictException */
    public function save(User $user): int;

    /** @throws UserNotFoundException */
    public function update(User $user): void;

    /** @throws UserNotFoundException */
    public function delete(int $id): void;
}
