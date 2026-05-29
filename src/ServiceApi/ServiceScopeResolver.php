<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

/**
 * Maps a service-API request (path + method) to the {@see ServiceScope} it
 * requires. Returns null for non-`/api/` paths (the scope check does not apply).
 */
final class ServiceScopeResolver
{
    public static function resolve(string $path, string $method): ?ServiceScope
    {
        if (!str_starts_with($path, '/api/')) {
            return null;
        }

        $method = strtoupper($method);
        $isRead = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);

        if (str_starts_with($path, '/api/invoices')) {
            // Payments are written under /api/invoices/{id}/payments.
            if (str_contains($path, '/payments') && !$isRead) {
                return ServiceScope::WritePayments;
            }

            return $isRead ? ServiceScope::ReadInvoices : ServiceScope::WritePayments;
        }

        if (str_starts_with($path, '/api/clients')) {
            return ServiceScope::ReadInvoices;
        }

        return null;
    }
}
