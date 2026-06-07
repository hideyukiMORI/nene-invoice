<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Item;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Item\ImportItemsCsvUseCase;
use NeneInvoice\Item\Item;
use NeneInvoice\Item\ItemImportTemplate;
use NeneInvoice\Item\ItemListFilter;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryItemRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ImportItemsCsvUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryItemRepository $repo;
    private RecordingAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo  = new InMemoryItemRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
    }

    private function useCase(): ImportItemsCsvUseCase
    {
        return new ImportItemsCsvUseCase(
            $this->repo,
            new ImmediateTransactionManager(),
            fn () => $this->repo,
            fn () => $this->audit,
            $this->holder,
        );
    }

    /**
     * @param list<list<string>> $rows
     */
    private function csv(array $rows): string
    {
        $lines = [implode(',', ItemImportTemplate::HEADER)];
        foreach ($rows as $r) {
            $lines[] = implode(',', $r);
        }

        return implode("\n", $lines) . "\n";
    }

    private function itemCount(): int
    {
        return $this->repo->countForAdminList(new ItemListFilter());
    }

    public function test_creates_items_with_percent_to_bps_and_yen_one_to_one(): void
    {
        $result = $this->useCase()->execute(7, $this->csv([
            ['items/v1', '', '保守サポート', '50000', '10'],
            ['items/v1', '', '軽減税率品', '1000', '8'],
        ]), false);

        self::assertTrue($result->accepted);
        self::assertSame(2, $result->created);
        self::assertSame(2, $this->itemCount());

        $items = $this->repo->findForExport(new ItemListFilter());
        $byDesc = [];
        foreach ($items as $i) {
            $byDesc[$i->description] = $i;
        }
        // Yen stored 1:1 as cents; percent mapped to bps.
        self::assertSame(50000, $byDesc['保守サポート']->defaultUnitPriceCents);
        self::assertSame(1000, $byDesc['保守サポート']->defaultTaxRateBps);
        self::assertSame(800, $byDesc['軽減税率品']->defaultTaxRateBps);
    }

    public function test_accepts_percent_with_sign(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['items/v1', '', 'x', '100', '8%'],
        ]), false);

        self::assertTrue($result->accepted);
        self::assertSame(800, $this->repo->findForExport(new ItemListFilter())[0]->defaultTaxRateBps);
    }

    public function test_updates_existing_by_id(): void
    {
        $id = $this->repo->save(new Item(organizationId: 1, description: 'Before', defaultUnitPriceCents: 100, defaultTaxRateBps: 1000));

        $result = $this->useCase()->execute(9, $this->csv([
            ['items/v1', (string) $id, 'After', '200', '10'],
        ]), false);

        self::assertTrue($result->accepted);
        self::assertSame(1, $result->updated);
        self::assertSame('After', $this->repo->findById($id)?->description);
        self::assertSame(200, $this->repo->findById($id)?->defaultUnitPriceCents);
    }

    public function test_invalid_tax_rate_rejects_whole_file(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['items/v1', '', 'Good', '100', '10'],
            ['items/v1', '', 'Bad', '100', '5'],
        ]), false);

        self::assertFalse($result->accepted);
        self::assertSame(0, $this->itemCount());
        self::assertSame('invalid_tax_rate', $result->errors[0]['code']);
        self::assertSame(3, $result->errors[0]['row']);
    }

    public function test_negative_or_nonnumeric_price_is_rejected(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['items/v1', '', 'x', '-5', '10'],
        ]), false);

        self::assertFalse($result->accepted);
        self::assertSame('invalid_price', $result->errors[0]['code']);
    }

    public function test_dry_run_validates_without_writing(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['items/v1', '', '保守', '100', '10'],
        ]), true);

        self::assertTrue($result->accepted);
        self::assertTrue($result->dryRun);
        self::assertSame(0, $this->itemCount());
        self::assertCount(0, $this->audit->records);
    }

    public function test_rejects_wrong_header(): void
    {
        $result = $this->useCase()->execute(1, "description,price\nx,1\n", false);

        self::assertFalse($result->accepted);
        self::assertNotNull($result->formatError);
    }
}
