<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Builds an {@see AuditLogFilter} from raw query parameters. Shared by the list
 * and CSV-export handlers so both interpret the same filters identically.
 */
final class AuditLogFilterFactory
{
    /**
     * @param array<string, mixed> $query
     */
    public static function fromQueryParams(array $query): AuditLogFilter
    {
        return new AuditLogFilter(
            entityType: self::stringParam($query, 'entity_type'),
            action: self::stringParam($query, 'action'),
            actorUserId: isset($query['actor_user_id']) && is_numeric($query['actor_user_id'])
                ? (int) $query['actor_user_id']
                : null,
            createdFrom: self::dateParam($query, 'created_from', false),
            createdTo: self::dateParam($query, 'created_to', true),
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function stringParam(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalizes a `YYYY-MM-DD` (or full datetime) bound into an inclusive
     * `Y-m-d H:i:s` comparison value. A date-only `endOfDay` bound expands to
     * `23:59:59` so the whole day is included. Invalid input is ignored.
     *
     * @param array<string, mixed> $query
     */
    private static function dateParam(array $query, string $key, bool $endOfDay): ?string
    {
        $value = self::stringParam($query, $key);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
