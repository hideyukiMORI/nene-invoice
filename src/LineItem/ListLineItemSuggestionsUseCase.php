<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * Builds history-based line-item suggestions for the caller's organization
 * (#315, Phase 1). Reads recent line items (newest first) and folds them by
 * description: usage count is the number of occurrences, and the default price /
 * tax rate are taken from the most recent occurrence (recency tracks current
 * pricing better than an all-time mode). No dedicated item master is involved.
 */
final readonly class ListLineItemSuggestionsUseCase
{
    /**
     * How many recent rows to scan. Generous enough to surface real repeats
     * while keeping the per-request aggregation cheap.
     */
    private const SCAN_LIMIT = 500;

    /** Upper bound on suggestions returned to the client. */
    private const MAX_SUGGESTIONS = 100;

    public function __construct(
        private LineItemRepositoryInterface $lineItems,
    ) {
    }

    /** @return list<LineItemSuggestion> ordered by usage desc, then recency */
    public function execute(): array
    {
        $rows = $this->lineItems->recentForOrganization(self::SCAN_LIMIT);

        // Rows arrive newest-first, so the first time we see a description its
        // values are the most recent — keep those as the defaults and only bump
        // the count on later (older) occurrences.
        /** @var array<string, array{suggestion: LineItemSuggestion, rank: int}> $byDescription */
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
                    ),
                    'rank' => $rank++,
                ];
                continue;
            }

            $existing = $byDescription[$key]['suggestion'];
            $byDescription[$key]['suggestion'] = new LineItemSuggestion(
                description: $existing->description,
                unitPriceCents: $existing->unitPriceCents,
                taxRateBps: $existing->taxRateBps,
                usageCount: $existing->usageCount + 1,
            );
        }

        $entries = array_values($byDescription);

        // Most-used first; ties broken by recency (lower rank = seen sooner =
        // more recent) so the ordering is deterministic.
        usort(
            $entries,
            static function (array $a, array $b): int {
                $byUsage = $b['suggestion']->usageCount <=> $a['suggestion']->usageCount;

                return $byUsage !== 0 ? $byUsage : $a['rank'] <=> $b['rank'];
            },
        );

        $suggestions = array_map(
            static fn (array $entry): LineItemSuggestion => $entry['suggestion'],
            $entries,
        );

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
    }
}
