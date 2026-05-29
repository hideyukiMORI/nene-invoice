<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use NeneInvoice\User\User;
use NeneInvoice\User\UserRepositoryInterface;

final readonly class GetCurrentUserUseCase implements GetCurrentUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(int $userId): ?User
    {
        return $this->users->findById($userId);
    }
}
