<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteOrganizationUseCase
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private AuditRecorderInterface $audit,
    ) {
    }

    /** @throws OrganizationNotFoundException */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->organizations->findById($id);

        if ($existing === null) {
            throw new OrganizationNotFoundException($id);
        }

        $this->organizations->delete($id);

        $this->audit->record($actorUserId, $id, 'organization.deleted', 'organization', $id, OrganizationResponse::toArray($existing), null);
    }
}
