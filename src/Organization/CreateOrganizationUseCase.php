<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\User\UserResponse;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizationsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param Closure(DatabaseQueryExecutorInterface): InitialAdminRepositoryInterface $initialAdminFactory
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $organizationsFactory,
        private Closure $auditFactory,
        private Closure $initialAdminFactory,
    ) {
    }

    /**
     * Creates a tenant and, when the input carries both an admin email and
     * password, provisions that tenant's first admin in the SAME transaction so
     * there can be no orphan org or orphan admin. The admin is always bound to
     * the org id returned by the org insert — never to the caller's org — which
     * is what makes this a safe superadmin cross-tenant operation (ADR 0006).
     *
     * Both-or-neither of the admin fields is enforced at the HTTP boundary
     * (422); here we simply treat "both present" as the provisioning path.
     *
     * @throws OrganizationSlugConflictException
     * @throws \NeneInvoice\User\UserEmailConflictException when the admin email is taken
     */
    public function execute(?int $actorUserId, CreateOrganizationInput $input): Organization
    {
        $adminEmail    = $input->adminEmail;
        $adminPassword = $input->adminPassword;

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $input, $adminEmail, $adminPassword): Organization {
            $organizations = ($this->organizationsFactory)($exec);
            $audit         = ($this->auditFactory)($exec);

            $id = $organizations->save(new Organization(
                name: $input->name,
                slug: $input->slug,
                plan: $input->plan,
                isActive: true,
            ));

            $created = $organizations->findById($id);

            if ($created === null) {
                throw new LogicException('Organization disappeared immediately after creation.');
            }

            $audit->record($actorUserId, $id, 'organization.created', 'organization', $id, null, OrganizationResponse::toArray($created));

            if ($adminEmail !== null && $adminPassword !== null) {
                // The org id comes from the insert above, so the admin can only
                // ever land in the just-created tenant.
                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                $admin = ($this->initialAdminFactory)($exec)->createInitialAdmin($id, $adminEmail, $passwordHash);

                $audit->record($actorUserId, $id, 'user.created', 'user', $admin->id, null, UserResponse::toArray($admin));
            }

            return $created;
        });
    }
}
