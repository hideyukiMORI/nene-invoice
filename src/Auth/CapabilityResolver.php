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

        if (str_starts_with($path, '/admin/company-settings')) {
            return Capability::ManageCompanySettings;
        }

        if (str_starts_with($path, '/admin/dashboard')) {
            return Capability::ViewBilling;
        }

        foreach (['/admin/clients', '/admin/quotes', '/admin/invoices', '/admin/payments', '/admin/line-items'] as $prefix) {
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
