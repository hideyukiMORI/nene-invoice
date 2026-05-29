<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * Billing-specific capabilities enforced per route (ADR 0006).
 *
 * String values are registered in `docs/explanation/terminology.md` (binding).
 */
enum Capability: string
{
    case ManageOrganizations = 'manage_organizations';
    case ManageUsers = 'manage_users';
    case ManageCompanySettings = 'manage_company_settings';
    case ManageBilling = 'manage_billing';
    case ViewBilling = 'view_billing';
}
