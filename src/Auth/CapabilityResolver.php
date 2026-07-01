<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * Maps an HTTP request (path + method) to the {@see Capability} it requires.
 *
 * Returns `null` when a route needs no specific capability (public routes, or
 * authenticated self-service such as `GET /admin/me`).
 */
final class CapabilityResolver
{
    public static function resolve(string $path, string $method): ?Capability
    {
        $method = strtoupper($method);

        if (str_starts_with($path, '/admin/organizations')) {
            return Capability::ManageOrganizations;
        }

        if (str_starts_with($path, '/admin/users')) {
            return Capability::ManageUsers;
        }

        // Audit trail is admin oversight (read-only); gated at the admin level,
        // not for billing operators (member / viewer).
        if (str_starts_with($path, '/admin/audit-logs')) {
            return Capability::ManageUsers;
        }

        // Service-token management (NeNe Clear integration credentials) is admin
        // oversight: issuing a machine principal that can write payments must not
        // be available to billing operators (member / viewer). ADR 0009 ops.
        if (str_starts_with($path, '/admin/service-tokens')) {
            return Capability::ManageUsers;
        }

        if (str_starts_with($path, '/admin/company-settings')) {
            return Capability::ManageCompanySettings;
        }

        // Payment-gateway configuration is issuer-level setup (keys live in env;
        // these endpoints only read status + run a connectivity test).
        if (str_starts_with($path, '/admin/gateway-settings')) {
            return Capability::ManageCompanySettings;
        }

        if (str_starts_with($path, '/admin/dashboard')) {
            return Capability::ViewBilling;
        }

        foreach (['/admin/clients', '/admin/items', '/admin/templates', '/admin/quotes', '/admin/recurring-invoices', '/admin/invoices', '/admin/payments', '/admin/payment-links', '/admin/bank-transactions', '/admin/line-items'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return self::isReadMethod($method) ? Capability::ViewBilling : Capability::ManageBilling;
            }
        }

        // /admin/me and any other route: no specific capability required.
        return null;
    }

    private static function isReadMethod(string $method): bool
    {
        return in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
