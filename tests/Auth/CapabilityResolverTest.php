<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use NeneInvoice\Auth\Capability;
use NeneInvoice\Auth\CapabilityResolver;
use PHPUnit\Framework\TestCase;

final class CapabilityResolverTest extends TestCase
{
    public function test_maps_admin_paths_to_capabilities(): void
    {
        self::assertSame(Capability::ManageOrganizations, CapabilityResolver::resolve('/admin/organizations', 'GET'));
        self::assertSame(Capability::ManageOrganizations, CapabilityResolver::resolve('/admin/organizations/5', 'DELETE'));
        self::assertSame(Capability::ManageUsers, CapabilityResolver::resolve('/admin/users', 'POST'));
        self::assertSame(Capability::ManageCompanySettings, CapabilityResolver::resolve('/admin/company-settings', 'PUT'));
    }

    public function test_audit_logs_require_admin_oversight_capability(): void
    {
        self::assertSame(Capability::ManageUsers, CapabilityResolver::resolve('/admin/audit-logs', 'GET'));
    }

    public function test_billing_paths_split_read_and_write(): void
    {
        self::assertSame(Capability::ViewBilling, CapabilityResolver::resolve('/admin/invoices', 'GET'));
        self::assertSame(Capability::ManageBilling, CapabilityResolver::resolve('/admin/invoices', 'POST'));
        self::assertSame(Capability::ViewBilling, CapabilityResolver::resolve('/admin/quotes/1', 'GET'));
        self::assertSame(Capability::ManageBilling, CapabilityResolver::resolve('/admin/payments', 'POST'));
    }

    public function test_self_service_and_public_paths_need_no_capability(): void
    {
        self::assertNull(CapabilityResolver::resolve('/admin/me', 'GET'));
        self::assertNull(CapabilityResolver::resolve('/health', 'GET'));
        self::assertNull(CapabilityResolver::resolve('/auth/login', 'POST'));
    }
}
