<?php

declare(strict_types=1);

namespace NeneInvoice\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /** @return list<User> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId): int;

    /** @throws UserEmailConflictException */
    public function save(User $user): int;

    /** @throws UserNotFoundException */
    public function update(User $user): void;

    /** @throws UserNotFoundException */
    public function delete(int $id): void;
}
