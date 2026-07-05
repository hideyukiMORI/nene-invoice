<?php

declare(strict_types=1);

namespace NeneInvoice\Install;

/**
 * What {@see InstallApplication::install()} provisioned. The `*Created` flags are
 * false when an idempotent re-run found the row already present (concurrent
 * double-submit), so callers can report "created" vs "already existed" without a
 * second query. `organizationId` is null for a multi-tenant (superadmin) install.
 */
final readonly class InstallResult
{
    public function __construct(
        public ?int $organizationId,
        public bool $organizationCreated,
        public string $adminEmail,
        public bool $adminCreated,
        public bool $isSingle,
    ) {
    }
}
