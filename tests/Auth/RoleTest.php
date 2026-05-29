<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use NeneInvoice\Auth\Capability;
use NeneInvoice\Auth\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_superadmin_has_every_capability(): void
    {
        foreach (Capability::cases() as $capability) {
            self::assertTrue(Role::Superadmin->hasCapability($capability), $capability->value);
        }
    }

    public function test_admin_has_all_capabilities_except_managing_organizations(): void
    {
        self::assertFalse(Role::Admin->hasCapability(Capability::ManageOrganizations));
        self::assertTrue(Role::Admin->hasCapability(Capability::ManageUsers));
        self::assertTrue(Role::Admin->hasCapability(Capability::ManageCompanySettings));
        self::assertTrue(Role::Admin->hasCapability(Capability::ManageBilling));
        self::assertTrue(Role::Admin->hasCapability(Capability::ViewBilling));
    }

    public function test_member_can_only_operate_billing(): void
    {
        self::assertTrue(Role::Member->hasCapability(Capability::ManageBilling));
        self::assertTrue(Role::Member->hasCapability(Capability::ViewBilling));
        self::assertFalse(Role::Member->hasCapability(Capability::ManageUsers));
        self::assertFalse(Role::Member->hasCapability(Capability::ManageCompanySettings));
        self::assertFalse(Role::Member->hasCapability(Capability::ManageOrganizations));
    }

    public function test_viewer_is_read_only(): void
    {
        self::assertTrue(Role::Viewer->hasCapability(Capability::ViewBilling));
        self::assertFalse(Role::Viewer->hasCapability(Capability::ManageBilling));
        self::assertFalse(Role::Viewer->hasCapability(Capability::ManageUsers));
        self::assertFalse(Role::Viewer->hasCapability(Capability::ManageCompanySettings));
        self::assertFalse(Role::Viewer->hasCapability(Capability::ManageOrganizations));
    }
}
