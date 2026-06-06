<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\LineItem;

use NeneInvoice\Item\Item;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\LineItemSuggestionSource;
use NeneInvoice\LineItem\ListLineItemSuggestionsUseCase;
use NeneInvoice\Tests\Support\InMemoryItemRepository;
use PHPUnit\Framework\TestCase;

final class ListLineItemSuggestionsUseCaseTest extends TestCase
{
    public function test_groups_by_description_counts_usage_and_orders_by_most_used(): void
    {
        // Rows arrive newest-first (as the repository returns them).
        $useCase = $this->useCase([
            ['description' => 'Consulting', 'unit_price_cents' => 15000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-05 10:00:00'],
            ['description' => 'Design',     'unit_price_cents' => 8000,  'tax_rate_bps' => 800,  'created_at' => '2026-06-04 10:00:00'],
            ['description' => 'Consulting', 'unit_price_cents' => 12000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-03 10:00:00'],
            ['description' => 'Consulting', 'unit_price_cents' => 10000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-02 10:00:00'],
        ]);

        $suggestions = $useCase->execute();

        self::assertCount(2, $suggestions);
        // Consulting (3 uses) ranks above Design (1 use).
        self::assertSame('Consulting', $suggestions[0]->description);
        self::assertSame(3, $suggestions[0]->usageCount);
        self::assertSame(LineItemSuggestionSource::History, $suggestions[0]->source);
        // Default price/rate come from the most recent occurrence (15000, not 10000).
        self::assertSame(15000, $suggestions[0]->unitPriceCents);
        self::assertSame(1000, $suggestions[0]->taxRateBps);
        self::assertSame('Design', $suggestions[1]->description);
        self::assertSame(1, $suggestions[1]->usageCount);
    }

    public function test_folds_case_and_whitespace_variants_keeping_first_seen_label(): void
    {
        $useCase = $this->useCase([
            ['description' => 'Web制作', 'unit_price_cents' => 50000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-05 10:00:00'],
            ['description' => '  web制作 ', 'unit_price_cents' => 40000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-04 10:00:00'],
        ]);

        $suggestions = $useCase->execute();

        self::assertCount(1, $suggestions);
        self::assertSame('Web制作', $suggestions[0]->description); // first-seen (most recent) label
        self::assertSame(2, $suggestions[0]->usageCount);
    }

    public function test_skips_blank_descriptions(): void
    {
        $useCase = $this->useCase([
            ['description' => '   ', 'unit_price_cents' => 1000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-05 10:00:00'],
        ]);

        self::assertSame([], $useCase->execute());
    }

    public function test_master_items_lead_with_their_own_defaults_and_history_usage(): void
    {
        // History has a cheaper/older price for the same description; the master
        // is authoritative for the defaults but inherits the history usage count.
        $master = new InMemoryItemRepository();
        $master->save(new Item(organizationId: 1, description: '保守サポート', defaultUnitPriceCents: 50000, defaultTaxRateBps: 1000));

        $useCase = $this->useCase(
            [
                ['description' => '保守サポート', 'unit_price_cents' => 30000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-05 10:00:00'],
                ['description' => 'スポット作業', 'unit_price_cents' => 9000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-04 10:00:00'],
            ],
            $master,
        );

        $suggestions = $useCase->execute();

        self::assertCount(2, $suggestions);
        // Master row leads, uses its own default price (50000, not history 30000).
        self::assertSame('保守サポート', $suggestions[0]->description);
        self::assertSame(LineItemSuggestionSource::Master, $suggestions[0]->source);
        self::assertSame(50000, $suggestions[0]->unitPriceCents);
        self::assertSame(1, $suggestions[0]->usageCount); // history usage attached
        // History-only description falls through after the master.
        self::assertSame('スポット作業', $suggestions[1]->description);
        self::assertSame(LineItemSuggestionSource::History, $suggestions[1]->source);
    }

    public function test_unused_master_item_appears_with_zero_usage(): void
    {
        $master = new InMemoryItemRepository();
        $master->save(new Item(organizationId: 1, description: '新サービス', defaultUnitPriceCents: 12000, defaultTaxRateBps: 800));

        $suggestions = $this->useCase([], $master)->execute();

        self::assertCount(1, $suggestions);
        self::assertSame('新サービス', $suggestions[0]->description);
        self::assertSame(0, $suggestions[0]->usageCount);
        self::assertSame(LineItemSuggestionSource::Master, $suggestions[0]->source);
    }

    /**
     * @param list<array{description: string, unit_price_cents: int, tax_rate_bps: int, created_at: string}> $rows
     */
    private function useCase(array $rows, ?InMemoryItemRepository $master = null): ListLineItemSuggestionsUseCase
    {
        return new ListLineItemSuggestionsUseCase(
            $this->repoReturning($rows),
            $master ?? new InMemoryItemRepository(),
        );
    }

    /**
     * @param list<array{description: string, unit_price_cents: int, tax_rate_bps: int, created_at: string}> $rows
     */
    private function repoReturning(array $rows): LineItemRepositoryInterface
    {
        return new class ($rows) implements LineItemRepositoryInterface {
            /** @param list<array{description: string, unit_price_cents: int, tax_rate_bps: int, created_at: string}> $rows */
            public function __construct(private readonly array $rows)
            {
            }

            public function findByParent(LineItemParent $parentType, int $parentId): array
            {
                return [];
            }

            public function replaceForParent(LineItemParent $parentType, int $parentId, array $lines): void
            {
            }

            public function deleteForParent(LineItemParent $parentType, int $parentId): void
            {
            }

            /** @return list<array{description: string, unit_price_cents: int, tax_rate_bps: int, created_at: string}> */
            public function recentForOrganization(int $limit): array
            {
                return array_slice($this->rows, 0, $limit);
            }
        };
    }
}
