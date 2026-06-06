<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Deletes a user within the caller's organization. Cross-organization targets
     * are reported as not found, and a caller cannot delete their own account.
     *
     * @throws CannotDeleteSelfException
     * @throws UserNotFoundException
     */
    public function execute(int $callerUserId, int $userId): void
    {
        if ($userId === $callerUserId) {
            throw new CannotDeleteSelfException();
        }

        $existing = $this->users->findInOrganization($userId);

        if ($existing === null) {
            throw new UserNotFoundException($userId);
        }

        $this->users->delete($userId);

        $this->audit->record($callerUserId, $this->orgId->get(), 'user.deleted', 'user', $userId, UserResponse::toArray($existing), null);
    }
}
