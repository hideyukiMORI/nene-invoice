<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Template;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\Template\CreateTemplateInput;
use NeneInvoice\Template\CreateTemplateUseCase;
use NeneInvoice\Template\DeleteTemplateUseCase;
use NeneInvoice\Template\GetTemplateByIdUseCase;
use NeneInvoice\Template\Template;
use NeneInvoice\Template\TemplateNotFoundException;
use NeneInvoice\Template\UpdateTemplateInput;
use NeneInvoice\Template\UpdateTemplateUseCase;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryTemplateRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class TemplateWriteUseCasesTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryTemplateRepository $templates;
    private InMemoryLineItemRepository $lines;
    private RecordingAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->templates = new InMemoryTemplateRepository($this->holder);
        $this->lines = new InMemoryLineItemRepository();
        $this->audit = new RecordingAuditRecorder();
    }

    public function test_create_persists_header_and_line_presets_and_audits(): void
    {
        $this->holder->set(3);

        $result = $this->create()->execute(7, new CreateTemplateInput(
            name: '月次保守',
            lines: [new LineItemInput('保守サポート', 1, 50000, 1000)],
            notes: '毎月',
        ));

        self::assertSame(3, $result->template->organizationId);
        self::assertCount(1, $result->lines);
        self::assertSame('保守サポート', $result->lines[0]->description);
        self::assertSame(LineItemParent::Template, $result->lines[0]->parentType);

        self::assertSame('template.created', $this->audit->records[0]['action']);
    }

    public function test_get_returns_lines_and_blocks_cross_org(): void
    {
        $id = $this->templates->save(new Template(organizationId: 1, name: 'T'));
        $this->lines->replaceForParent(LineItemParent::Template, $id, []);

        $result = $this->get()->execute($id);
        self::assertSame('T', $result->template->name);

        $other = $this->templates->save(new Template(organizationId: 2, name: 'Other'));
        $this->expectException(TemplateNotFoundException::class);
        $this->get()->execute($other);
    }

    public function test_update_replaces_lines_and_records_before_after(): void
    {
        $created = $this->create()->execute(1, new CreateTemplateInput(
            name: 'Before',
            lines: [new LineItemInput('Old', 1, 1000, 1000)],
        ));
        $id = $created->template->id ?? 0;

        $updated = $this->update()->execute(1, $id, new UpdateTemplateInput(
            name: 'After',
            lines: [new LineItemInput('New A', 2, 2000, 800), new LineItemInput('New B', 1, 500, 1000)],
        ));

        self::assertSame('After', $updated->template->name);
        self::assertCount(2, $updated->lines);
        self::assertSame('template.updated', $this->audit->records[1]['action']);
        self::assertSame('Before', $this->audit->records[1]['before']['name'] ?? null);
    }

    public function test_delete_soft_deletes_and_clears_lines(): void
    {
        $created = $this->create()->execute(1, new CreateTemplateInput(
            name: 'Doomed',
            lines: [new LineItemInput('X', 1, 1000, 1000)],
        ));
        $id = $created->template->id ?? 0;

        $this->delete()->execute(5, $id);

        self::assertNull($this->templates->findById($id));
        self::assertCount(0, $this->lines->findByParent(LineItemParent::Template, $id));
        self::assertSame('template.deleted', $this->audit->records[1]['action']);
    }

    private function create(): CreateTemplateUseCase
    {
        return new CreateTemplateUseCase($this->templates, $this->lines, $this->audit, $this->holder);
    }

    private function get(): GetTemplateByIdUseCase
    {
        return new GetTemplateByIdUseCase($this->templates, $this->lines);
    }

    private function update(): UpdateTemplateUseCase
    {
        return new UpdateTemplateUseCase($this->templates, $this->lines, $this->audit, $this->holder);
    }

    private function delete(): DeleteTemplateUseCase
    {
        return new DeleteTemplateUseCase($this->templates, $this->lines, $this->audit, $this->holder);
    }
}
