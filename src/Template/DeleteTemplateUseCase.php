<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class DeleteTemplateUseCase
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private TemplateRepositoryInterface $templates,
        private LineItemRepositoryInterface $lineItems,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Soft-deletes a template and clears its line presets, in the resolved
     * organization (the repository scopes the lookup/delete to the request org).
     *
     * @throws TemplateNotFoundException
     */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->templates->findById($id);
        if ($existing === null) {
            throw new TemplateNotFoundException($id);
        }

        $before = TemplateResponse::toArray($existing, $this->lineItems->findByParent(LineItemParent::Template, $id));

        $this->templates->delete($id);
        $this->lineItems->deleteForParent(LineItemParent::Template, $id);

        $this->audit->record($actorUserId, $this->orgId->get(), 'template.deleted', 'template', $id, $before, null);
    }
}
