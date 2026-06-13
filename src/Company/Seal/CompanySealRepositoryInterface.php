<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

/**
 * Stores one company seal (社印) PNG per organization, base64-encoded. All
 * operations are scoped to the request's organization (multi-tenant — ADR 0006).
 */
interface CompanySealRepositoryInterface
{
    /** The stored seal as a base64 PNG string, or null when none is set. */
    public function find(): ?string;

    public function exists(): bool;

    /** Upserts the seal image for the caller's organization. */
    public function save(string $imageBase64): void;

    /** Removes the seal image for the caller's organization (no-op when absent). */
    public function delete(): void;
}
