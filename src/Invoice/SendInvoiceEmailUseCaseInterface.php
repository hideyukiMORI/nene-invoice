<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface SendInvoiceEmailUseCaseInterface
{
    public function execute(?int $actorUserId, int $invoiceId): void;

    /**
     * Builds the email preview (recipient / subject / body) without sending or
     * auditing. Used by demo organizations (#626). Applies the same sendability
     * / client-email guards as {@see execute()}.
     */
    public function preview(int $invoiceId): SendInvoiceEmailPreview;
}
