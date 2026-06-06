<?php

declare(strict_types=1);

namespace NeneInvoice\User;

final readonly class GetUserByIdUseCase implements GetUserByIdUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * Fetches a user that belongs to the resolved organization. A user from
     * another organization (or a missing id) is reported as not found so
     * cross-tenant existence is not leaked.
     *
     * @throws UserNotFoundException
     */
    public function execute(int $id): User
    {
        $user = $this->users->findInOrganization($id);

        if ($user === null) {
            throw new UserNotFoundException($id);
        }

        return $user;
    }
}
