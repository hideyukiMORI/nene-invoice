<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\LineItem;

use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\ListLineItemSuggestionsUseCase;
use PHPUnit\Framework\TestCase;

final class ListLineItemSuggestionsUseCaseTest extends TestCase
{
    public function test_groups_by_description_counts_usage_and_orders_by_most_used(): void
    {
        // Rows arrive newest-first (as the repository returns them).
        $useCase = new ListLineItemSuggestionsUseCase($this->repoReturning([
            ['description' => 'Consulting', 'unit_price_cents' => 15000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-05 10:00:00'],
            ['description' => 'Design',     'unit_price_cents' => 8000,  'tax_rate_bps' => 800,  'created_at' => '2026-06-04 10:00:00'],
            ['description' => 'Consulting', 'unit_price_cents' => 12000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-03 10:00:00'],
            ['description' => 'Consulting', 'unit_price_cents' => 10000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-02 10:00:00'],
        ]));

        $suggestions = $useCase->execute();

        self::assertCount(2, $suggestions);
        // Consulting (3 uses) ranks above Design (1 use).
        self::assertSame('Consulting', $suggestions[0]->description);
        self::assertSame(3, $suggestions[0]->usageCount);
        // Default price/rate come from the most recent occurrence (15000, not 10000).
        self::assertSame(15000, $suggestions[0]->unitPriceCents);
        self::assertSame(1000, $suggestions[0]->taxRateBps);
        self::assertSame('Design', $suggestions[1]->description);
        self::assertSame(1, $suggestions[1]->usageCount);
    }

    public function test_folds_case_and_whitespace_variants_keeping_first_seen_label(): void
    {
        $useCase = new ListLineItemSuggestionsUseCase($this->repoReturning([
            ['description' => 'Web制作', 'unit_price_cents' => 50000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-05 10:00:00'],
            ['description' => '  web制作 ', 'unit_price_cents' => 40000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-04 10:00:00'],
        ]));

        $suggestions = $useCase->execute();

        self::assertCount(1, $suggestions);
        self::assertSame('Web制作', $suggestions[0]->description); // first-seen (most recent) label
        self::assertSame(2, $suggestions[0]->usageCount);
    }

    public function test_skips_blank_descriptions(): void
    {
        $useCase = new ListLineItemSuggestionsUseCase($this->repoReturning([
            ['description' => '   ', 'unit_price_cents' => 1000, 'tax_rate_bps' => 1000, 'created_at' => '2026-06-05 10:00:00'],
        ]));

        self::assertSame([], $useCase->execute());
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
