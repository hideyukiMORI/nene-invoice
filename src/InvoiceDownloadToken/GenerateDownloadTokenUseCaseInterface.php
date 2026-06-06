<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

interface GenerateDownloadTokenUseCaseInterface
{
    /** @return array{rawToken: string, expiresAt: string} */
    public function execute(?int $actorUserId, int $invoiceId): array;
}
