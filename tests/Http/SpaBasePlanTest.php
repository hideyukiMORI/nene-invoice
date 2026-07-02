<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\SpaBasePlan;
use PHPUnit\Framework\TestCase;

final class SpaBasePlanTest extends TestCase
{
    /** @var callable(string): bool */
    private $never;

    protected function setUp(): void
    {
        // Non-path modes must not touch the org repository.
        $this->never = function (string $slug): bool {
            self::fail("slug existence must not be checked outside path mode (got '{$slug}')");
        };
    }

    public function test_single_mode_uses_install_base_for_both_and_keeps_path(): void
    {
        $plan = SpaBasePlan::resolve('', '/dashboard', 'single', $this->never);

        self::assertSame('', $plan->assetBase);
        self::assertSame('', $plan->appBase);
        self::assertSame('/dashboard', $plan->spaPath);
    }

    public function test_single_mode_under_subdirectory(): void
    {
        $plan = SpaBasePlan::resolve('/invoice', '/invoices/5', 'single', $this->never);

        self::assertSame('/invoice', $plan->assetBase);
        self::assertSame('/invoice', $plan->appBase);
        self::assertSame('/invoices/5', $plan->spaPath);
    }

    public function test_subdomain_mode_never_prefixes_a_slug(): void
    {
        $plan = SpaBasePlan::resolve('', '/acme/dashboard', 'subdomain', $this->never);

        self::assertSame('', $plan->appBase);
        self::assertSame('/acme/dashboard', $plan->spaPath);
    }

    public function test_path_mode_real_slug_prefixes_app_base_and_strips_spa_path(): void
    {
        $plan = SpaBasePlan::resolve('', '/acme/dashboard', 'path', fn (string $s): bool => $s === 'acme');

        // Assets stay install-relative (single physical copy); router/API get the slug.
        self::assertSame('', $plan->assetBase);
        self::assertSame('/acme', $plan->appBase);
        self::assertSame('/dashboard', $plan->spaPath);
    }

    public function test_path_mode_api_path_under_slug_is_stripped_for_the_api_decision(): void
    {
        $plan = SpaBasePlan::resolve('', '/acme/admin/dashboard', 'path', fn (string $s): bool => $s === 'acme');

        self::assertSame('/acme', $plan->appBase);
        self::assertSame('/admin/dashboard', $plan->spaPath);
    }

    public function test_path_mode_slug_only_yields_root_spa_path(): void
    {
        $plan = SpaBasePlan::resolve('', '/acme', 'path', fn (string $s): bool => $s === 'acme');

        self::assertSame('/acme', $plan->appBase);
        self::assertSame('/', $plan->spaPath);
    }

    public function test_path_mode_unknown_first_segment_is_org_less(): void
    {
        // Superadmin SPA route (org-less): first segment is not a tenant slug.
        $plan = SpaBasePlan::resolve('', '/organizations', 'path', fn (string $s): bool => $s === 'acme');

        self::assertSame('', $plan->appBase);
        self::assertSame('/organizations', $plan->spaPath);
    }

    public function test_path_mode_root_is_org_less(): void
    {
        $plan = SpaBasePlan::resolve('', '/', 'path', fn (string $s): bool => $s === 'acme');

        self::assertSame('', $plan->appBase);
        self::assertSame('/', $plan->spaPath);
    }

    public function test_path_mode_composes_with_subdirectory_install(): void
    {
        // Installed under /invoice, tenant acme: app base is /invoice/acme.
        $plan = SpaBasePlan::resolve('/invoice', '/acme/invoices', 'path', fn (string $s): bool => $s === 'acme');

        self::assertSame('/invoice', $plan->assetBase);
        self::assertSame('/invoice/acme', $plan->appBase);
        self::assertSame('/invoices', $plan->spaPath);
    }
}
