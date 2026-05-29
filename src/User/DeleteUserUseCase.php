<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * Deletes a user within the caller's organization. Cross-organization targets
     * are reported as not found, and a caller cannot delete their own account.
     *
     * @throws CannotDeleteSelfException
     * @throws UserNotFoundException
     */
    public function execute(int $organizationId, int $callerUserId, int $userId): void
    {
        if ($userId === $callerUserId) {
            throw new CannotDeleteSelfException();
        }

        $existing = $this->users->findById($userId);

        if ($existing === null || $existing->organizationId !== $organizationId) {
            throw new UserNotFoundException($userId);
        }

        $this->users->delete($userId);

        $this->audit->record($callerUserId, $organizationId, 'user.deleted', 'user', $userId, UserResponse::toArray($existing), null);
    }
}
