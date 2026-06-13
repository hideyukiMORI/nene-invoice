<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

interface CompanySealUseCaseInterface
{
    /** The stored seal as a base64 PNG string, or null when none is set. */
    public function get(): ?string;

    /** Persists a validated seal image for the caller's organization. */
    public function save(?int $actorUserId, string $imageBase64): void;

    /** Removes the seal image for the caller's organization. */
    public function delete(?int $actorUserId): void;
}
