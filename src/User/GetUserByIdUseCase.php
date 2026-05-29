<?php

declare(strict_types=1);

namespace NeneInvoice\User;

final readonly class GetUserByIdUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * Fetches a user that belongs to the given organization. A user from another
     * organization (or a missing id) is reported as not found so cross-tenant
     * existence is not leaked.
     *
     * @throws UserNotFoundException
     */
    public function execute(int $organizationId, int $id): User
    {
        $user = $this->users->findById($id);

        if ($user === null || $user->organizationId !== $organizationId) {
            throw new UserNotFoundException($id);
        }

        return $user;
    }
}
