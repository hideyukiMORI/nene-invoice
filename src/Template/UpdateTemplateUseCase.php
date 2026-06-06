<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class UpdateTemplateUseCase
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
     * Updates a template (header + line presets) in the resolved organization.
     *
     * @throws TemplateNotFoundException
     */
    public function execute(?int $actorUserId, int $id, UpdateTemplateInput $input): TemplateWithLines
    {
        $existing = $this->templates->findById($id);
        if ($existing === null) {
            throw new TemplateNotFoundException($id);
        }

        $before = TemplateResponse::toArray($existing, $this->lineItems->findByParent(LineItemParent::Template, $id));

        $this->templates->update(new Template(
            organizationId: $existing->organizationId,
            name: $input->name,
            notes: $input->notes,
            isDeleted: false,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        ));

        $entities = [];
        foreach ($input->lines as $index => $line) {
            $entities[] = new LineItem(
                parentType: LineItemParent::Template,
                parentId: $id,
                description: $line->description,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                taxRateBps: $line->taxRateBps,
                sortOrder: $index,
            );
        }
        $this->lineItems->replaceForParent(LineItemParent::Template, $id, $entities);

        $updated = $this->templates->findById($id);
        if ($updated === null) {
            throw new LogicException('Template disappeared immediately after update.');
        }

        $lines = $this->lineItems->findByParent(LineItemParent::Template, $id);

        $this->audit->record($actorUserId, $this->orgId->get(), 'template.updated', 'template', $id, $before, TemplateResponse::toArray($updated, $lines));

        return new TemplateWithLines($updated, $lines);
    }
}
