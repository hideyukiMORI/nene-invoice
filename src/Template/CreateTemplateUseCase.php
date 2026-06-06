<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class CreateTemplateUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): TemplateRepositoryInterface $templatesFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $templatesFactory,
        private Closure $lineItemsFactory,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** Creates a template (header + line presets) atomically in the resolved organization. */
    public function execute(?int $actorUserId, CreateTemplateInput $input): TemplateWithLines
    {
        $organizationId = $this->orgId->get();

        $result = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $organizationId,
            $input,
        ): TemplateWithLines {
            $templates = ($this->templatesFactory)($exec);
            $lineItems = ($this->lineItemsFactory)($exec);

            $id = $templates->save(new Template(
                organizationId: $organizationId,
                name: $input->name,
                notes: $input->notes,
            ));

            $lineItems->replaceForParent(LineItemParent::Template, $id, self::toLineEntities($id, $input->lines));

            $created = $templates->findById($id);
            if ($created === null) {
                throw new LogicException('Template disappeared immediately after creation.');
            }

            return new TemplateWithLines($created, $lineItems->findByParent(LineItemParent::Template, $id));
        });

        $this->audit->record($actorUserId, $organizationId, 'template.created', 'template', $result->template->id, null, TemplateResponse::toArray($result->template, $result->lines));

        return $result;
    }

    /**
     * @param list<\NeneInvoice\LineItem\LineItemInput> $lines
     *
     * @return list<LineItem>
     */
    private static function toLineEntities(int $templateId, array $lines): array
    {
        $entities = [];
        foreach ($lines as $index => $line) {
            $entities[] = new LineItem(
                parentType: LineItemParent::Template,
                parentId: $templateId,
                description: $line->description,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                taxRateBps: $line->taxRateBps,
                sortOrder: $index,
            );
        }

        return $entities;
    }
}
