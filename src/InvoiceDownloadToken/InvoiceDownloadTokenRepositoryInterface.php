<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

interface InvoiceDownloadTokenRepositoryInterface
{
    public function save(InvoiceDownloadToken $token): int;

    public function findByHash(string $tokenHash): ?InvoiceDownloadToken;
}
