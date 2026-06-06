<?php

declare(strict_types=1);

namespace NeneInvoice\User;

final readonly class ListUsersUseCase implements ListUsersUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(int $limit, int $offset): ListUsersResult
    {
        return new ListUsersResult(
            $this->users->findAll($limit, $offset),
            $this->users->count(),
        );
    }
}
