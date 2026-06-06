<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use NeneInvoice\Item\ItemRepositoryInterface;

/**
 * Builds line-item suggestions for the caller's organization: the authoritative
 * item master (#323) first, then history-derived descriptions not already in the
 * master as a fallback (#315). History is folded by description — usage count is
 * the number of occurrences and the default price / tax come from the most
 * recent one (recency tracks current pricing). Master rows carry their own
 * defaults (the master is the source of truth) and the history usage count for
 * the same description, so a frequently-used master item still sorts high.
 */
final readonly class ListLineItemSuggestionsUseCase
{
    /**
     * How many recent rows to scan. Generous enough to surface real repeats
     * while keeping the per-request aggregation cheap.
     */
    private const SCAN_LIMIT = 500;

    /** How many master items to pull (org item catalogs are small in practice). */
    private const MASTER_LIMIT = 200;

    /** Upper bound on suggestions returned to the client. */
    private const MAX_SUGGESTIONS = 100;

    public function __construct(
        private LineItemRepositoryInterface $lineItems,
        private ItemRepositoryInterface $items,
    ) {
    }

    /** @return list<LineItemSuggestion> master first (by usage, then name), then history-only by usage */
    public function execute(): array
    {
        $history = $this->aggregateHistory();

        // Master is authoritative: emit every master row with its own defaults,
        // attaching the history usage for the same description (0 if never used).
        $masterEntries = [];
        $seen = [];
        foreach ($this->items->findAll(self::MASTER_LIMIT, 0) as $item) {
            $description = trim($item->description);
            if ($description === '') {
                continue;
            }

            $key = mb_strtolower($description);
            $seen[$key] = true;
            $masterEntries[] = [
                'suggestion' => new LineItemSuggestion(
                    description: $description,
                    unitPriceCents: $item->defaultUnitPriceCents,
                    taxRateBps: $item->defaultTaxRateBps,
                    usageCount: $history[$key]['usage'] ?? 0,
                    source: LineItemSuggestionSource::Master,
                ),
                'sortKey' => $description,
            ];
        }

        usort(
            $masterEntries,
            static function (array $a, array $b): int {
                $byUsage = $b['suggestion']->usageCount <=> $a['suggestion']->usageCount;

                return $byUsage !== 0 ? $byUsage : strcmp($a['sortKey'], $b['sortKey']);
            },
        );

        // History fallback: descriptions not covered by the master, by usage then
        // recency (lower rank = seen sooner = more recent).
        $historyEntries = array_values(array_filter(
            $history,
            static fn (array $h): bool => !isset($seen[mb_strtolower($h['suggestion']->description)]),
        ));

        usort(
            $historyEntries,
            static function (array $a, array $b): int {
                $byUsage = $b['suggestion']->usageCount <=> $a['suggestion']->usageCount;

                return $byUsage !== 0 ? $byUsage : $a['rank'] <=> $b['rank'];
            },
        );

        $suggestions = array_map(
            static fn (array $entry): LineItemSuggestion => $entry['suggestion'],
            [...$masterEntries, ...$historyEntries],
        );

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * Folds recent line items by normalized description.
     *
     * @return array<string, array{suggestion: LineItemSuggestion, usage: int, rank: int}>
     */
    private function aggregateHistory(): array
    {
        $rows = $this->lineItems->recentForOrganization(self::SCAN_LIMIT);

        /** @var array<string, array{suggestion: LineItemSuggestion, usage: int, rank: int}> $byDescription */
        $byDescription = [];
        $rank = 0;

        foreach ($rows as $row) {
            $description = trim($row['description']);
            if ($description === '') {
                continue;
            }

            $key = mb_strtolower($description);

            if (!isset($byDescription[$key])) {
                $byDescription[$key] = [
                    'suggestion' => new LineItemSuggestion(
                        description: $description,
                        unitPriceCents: $row['unit_price_cents'],
                        taxRateBps: $row['tax_rate_bps'],
                        usageCount: 1,
                        source: LineItemSuggestionSource::History,
                    ),
                    'usage' => 1,
                    'rank' => $rank++,
                ];
                continue;
            }

            $existing = $byDescription[$key]['suggestion'];
            $usage = $existing->usageCount + 1;
            $byDescription[$key]['suggestion'] = new LineItemSuggestion(
                description: $existing->description,
                unitPriceCents: $existing->unitPriceCents,
                taxRateBps: $existing->taxRateBps,
                usageCount: $usage,
                source: LineItemSuggestionSource::History,
            );
            $byDescription[$key]['usage'] = $usage;
        }

        return $byDescription;
    }
}
