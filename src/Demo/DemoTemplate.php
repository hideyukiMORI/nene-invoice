<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

/**
 * The industry templates a disposable demo organization can be seeded with.
 *
 * The value is the `{template}` segment of `GET /demo/{template}` and the key the
 * {@see DemoDataSeeder} switches on. Three industries were chosen (2026-07-07) as
 * the tax-accountant referral set: construction, building maintenance, and
 * production/consulting (with withholding tax).
 */
enum DemoTemplate: string
{
    case Kensetsu = 'kensetsu';
    case Bldmainte = 'bldmainte';
    case Seisaku = 'seisaku';

    /** Human label (Japanese) for the seeded organization / logging. */
    public function label(): string
    {
        return match ($this) {
            self::Kensetsu => '建設・工務店',
            self::Bldmainte => 'ビルメンテ・清掃',
            self::Seisaku => '制作・コンサル',
        };
    }
}
