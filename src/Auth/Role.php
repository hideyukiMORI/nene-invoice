<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * Operator roles and their capabilities (ADR 0006).
 *
 * - superadmin: cross-tenant; manages organizations (all capabilities)
 * - admin: organization-scoped; everything except managing organizations
 * - member: organization-scoped billing operator
 * - viewer: organization-scoped read-only
 *
 * String values are registered in `docs/explanation/terminology.md` (binding).
 */
enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function hasCapability(Capability $capability): bool
    {
        return match ($this) {
            self::Superadmin => true,
            self::Admin => $capability !== Capability::ManageOrganizations,
            self::Member => match ($capability) {
                Capability::ManageBilling,
                Capability::ViewBilling => true,
                Capability::ManageOrganizations,
                Capability::ManageUsers,
                Capability::ManageCompanySettings => false,
            },
            self::Viewer => $capability === Capability::ViewBilling,
        };
    }
}
