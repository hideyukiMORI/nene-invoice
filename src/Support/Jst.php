<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Japan Standard Time conversion helpers.
 *
 * The application stores instant timestamps in UTC (see
 * docs/adr/0010-utc-storage-jst-display.md). User-facing output — PDFs, CSV
 * exports, the admin UI — must show those instants in JST, and calendar-date
 * fields (発行日, 支払期限, 有効期限) must be derived from the **JST** wall clock,
 * never UTC, so the Japanese calendar day is correct around midnight.
 */
final class Jst
{
    public const ZONE = 'Asia/Tokyo';

    private static ?DateTimeZone $jst = null;
    private static ?DateTimeZone $utc = null;

    /**
     * Current JST calendar date (`Y-m-d`). For list-filter defaults and overdue
     * checks where the comparison column holds a JST calendar date.
     */
    public static function today(): string
    {
        return self::of(new DateTimeImmutable('now', self::utc()))->format('Y-m-d');
    }

    /** Current JST wall-clock datetime (`Y-m-d H:i:s`). */
    public static function nowString(): string
    {
        return self::of(new DateTimeImmutable('now', self::utc()))->format('Y-m-d H:i:s');
    }

    /** Parses a stored UTC datetime string and returns it as a JST instant. */
    public static function fromUtc(string $utcDateTime): DateTimeImmutable
    {
        return (new DateTimeImmutable($utcDateTime, self::utc()))->setTimezone(self::jst());
    }

    /** JST calendar date (`Y-m-d`) of a stored UTC datetime string. */
    public static function date(string $utcDateTime): string
    {
        return self::fromUtc($utcDateTime)->format('Y-m-d');
    }

    /** JST wall-clock datetime (`Y-m-d H:i:s`) of a stored UTC datetime string. */
    public static function dateTime(string $utcDateTime): string
    {
        return self::fromUtc($utcDateTime)->format('Y-m-d H:i:s');
    }

    /** Re-zones a UTC instant to JST (for calendar math on a known instant). */
    public static function of(DateTimeImmutable $utcInstant): DateTimeImmutable
    {
        return $utcInstant->setTimezone(self::jst());
    }

    /**
     * Renders any instant as a UTC `Y-m-d H:i:s` string. Used to turn a JST
     * wall-clock boundary into the UTC value stored in instant columns
     * (issued_at, paid_at) for range queries.
     */
    public static function toUtcString(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(self::utc())->format('Y-m-d H:i:s');
    }

    private static function jst(): DateTimeZone
    {
        return self::$jst ??= new DateTimeZone(self::ZONE);
    }

    private static function utc(): DateTimeZone
    {
        return self::$utc ??= new DateTimeZone('UTC');
    }
}
