<?php

declare(strict_types=1);

namespace NeneInvoice\User;

final readonly class ListUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(int $organizationId, int $limit, int $offset): ListUsersResult
    {
        return new ListUsersResult(
            $this->users->findAllByOrganization($organizationId, $limit, $offset),
            $this->users->countByOrganization($organizationId),
        );
    }
}
