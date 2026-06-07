<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Item;

use NeneInvoice\Item\ExportItemsCsvUseCase;
use NeneInvoice\Item\Item;
use NeneInvoice\Item\ItemListFilter;
use NeneInvoice\Tests\Support\InMemoryItemRepository;
use PHPUnit\Framework\TestCase;

final class ExportItemsCsvUseCaseTest extends TestCase
{
    public function test_emits_template_header_with_bom(): void
    {
        $csv = (new ExportItemsCsvUseCase(new InMemoryItemRepository()))->execute(new ItemListFilter());

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('__template,id,品目名,標準単価,標準税率', $csv);
    }

    public function test_emits_round_trippable_row_with_percent_and_yen(): void
    {
        $repo = new InMemoryItemRepository();
        $repo->save(new Item(organizationId: 1, description: '保守サポート', defaultUnitPriceCents: 50000, defaultTaxRateBps: 1000));

        $csv = (new ExportItemsCsvUseCase($repo))->execute(new ItemListFilter());

        // version + (no id assertion on value) + description + yen(1:1) + percent
        self::assertStringContainsString('items/v1', $csv);
        self::assertStringContainsString('保守サポート', $csv);
        self::assertStringContainsString('50000', $csv);
        self::assertMatchesRegularExpression('/,10\\R?$/m', $csv); // tax percent column = 10
    }

    public function test_neutralizes_formula_in_description(): void
    {
        $repo = new InMemoryItemRepository();
        $repo->save(new Item(organizationId: 1, description: '=DANGER', defaultUnitPriceCents: 0, defaultTaxRateBps: 800));

        $csv = (new ExportItemsCsvUseCase($repo))->execute(new ItemListFilter());

        self::assertStringContainsString("'=DANGER", $csv);
    }

    public function test_reflects_search_filter(): void
    {
        $repo = new InMemoryItemRepository();
        $repo->save(new Item(organizationId: 1, description: 'アルファ', defaultUnitPriceCents: 1, defaultTaxRateBps: 1000));
        $repo->save(new Item(organizationId: 1, description: 'ベータ', defaultUnitPriceCents: 1, defaultTaxRateBps: 1000));

        $csv = (new ExportItemsCsvUseCase($repo))->execute(new ItemListFilter('アルファ'));

        self::assertStringContainsString('アルファ', $csv);
        self::assertStringNotContainsString('ベータ', $csv);
    }
}
