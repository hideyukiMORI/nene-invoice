<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

/**
 * Persistence for the per-organization issuer profile. There is at most one row
 * per organization; {@see save()} upserts. Every query is scoped to the
 * organization held in the request-scoped org holder (ADR 0006) — the
 * organization is never passed as a method argument.
 */
interface CompanySettingsRepositoryInterface
{
    public function find(): ?CompanySettings;

    public function save(CompanySettings $settings): void;
}
