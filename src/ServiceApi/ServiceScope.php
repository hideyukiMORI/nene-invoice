<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

/**
 * Scopes a service token (machine principal) may carry (ADR 0009). These are the
 * service-to-service equivalent of human capabilities — a Clear token is scoped to
 * exactly the operations it needs.
 */
enum ServiceScope: string
{
    case ReadInvoices = 'read:invoices';
    case WritePayments = 'write:payments';
}
