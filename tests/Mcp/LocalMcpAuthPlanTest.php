<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Mcp;

use NeneInvoice\Mcp\LocalMcpAuthPlan;
use NeneInvoice\Mcp\LocalMcpConfigException;
use PHPUnit\Framework\TestCase;

/**
 * Proves the ADR 0021 option-C fail-closed auth decision is pure and correct:
 * pre-issued wins, dev-secret mint is opt-in, neither yields no token, and the
 * superadmin mint is refused unless the admin-exposure opt-in is also set.
 */
final class LocalMcpAuthPlanTest extends TestCase
{
    public function test_pre_issued_bearer_is_used_verbatim_and_wins_over_secret(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv([
            'NENE2_LOCAL_MCP_BEARER' => '  header.payload.sig  ',
            'NENE2_ALLOW_DEV_SECRET' => '1',
            'NENE2_LOCAL_JWT_SECRET' => 'dev-secret',
        ]);

        self::assertSame('header.payload.sig', $plan->preIssuedToken);
        self::assertNull($plan->mintClaims);
        self::assertTrue($plan->hasToken());
    }

    public function test_no_credentials_yields_no_token(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv([]);

        self::assertNull($plan->preIssuedToken);
        self::assertNull($plan->mintClaims);
        self::assertFalse($plan->hasToken());
    }

    public function test_dev_secret_without_allow_flag_yields_no_token(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv([
            'NENE2_LOCAL_JWT_SECRET' => 'dev-secret',
            // NENE2_ALLOW_DEV_SECRET not set
        ]);

        self::assertFalse($plan->hasToken());
    }

    public function test_allow_flag_without_secret_yields_no_token(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv(['NENE2_ALLOW_DEV_SECRET' => '1']);

        self::assertFalse($plan->hasToken());
    }

    public function test_mint_defaults_to_non_superadmin_admin_role_and_org_one(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv([
            'NENE2_ALLOW_DEV_SECRET' => '1',
            'NENE2_LOCAL_JWT_SECRET' => 'dev-secret',
        ]);

        self::assertNull($plan->preIssuedToken);
        self::assertSame(['sub' => 0, 'role' => 'admin', 'org' => 1], $plan->mintClaims);
    }

    public function test_mint_honours_explicit_org_sub_and_non_superadmin_role(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv([
            'NENE2_ALLOW_DEV_SECRET' => '1',
            'NENE2_LOCAL_JWT_SECRET' => 'dev-secret',
            'NENE2_LOCAL_MCP_ORG' => '7',
            'NENE2_LOCAL_MCP_SUB' => '42',
            'NENE2_LOCAL_MCP_ROLE' => 'viewer',
        ]);

        self::assertSame(['sub' => 42, 'role' => 'viewer', 'org' => 7], $plan->mintClaims);
    }

    public function test_superadmin_mint_is_refused_without_admin_exposure_optin(): void
    {
        $this->expectException(LocalMcpConfigException::class);
        $this->expectExceptionMessage('superadmin');

        LocalMcpAuthPlan::fromEnv([
            'NENE2_ALLOW_DEV_SECRET' => '1',
            'NENE2_LOCAL_JWT_SECRET' => 'dev-secret',
            'NENE2_LOCAL_MCP_ROLE' => 'superadmin',
            // NENE2_LOCAL_MCP_INCLUDE_ADMIN not set → refused
        ]);
    }

    public function test_superadmin_mint_allowed_only_through_triple_optin(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv([
            'NENE2_ALLOW_DEV_SECRET' => '1',
            'NENE2_LOCAL_JWT_SECRET' => 'dev-secret',
            'NENE2_LOCAL_MCP_ROLE' => 'superadmin',
            'NENE2_LOCAL_MCP_INCLUDE_ADMIN' => '1',
        ]);

        self::assertSame(['sub' => 0, 'role' => 'superadmin', 'org' => 1], $plan->mintClaims);
    }

    public function test_non_numeric_org_falls_back_to_default(): void
    {
        $plan = LocalMcpAuthPlan::fromEnv([
            'NENE2_ALLOW_DEV_SECRET' => '1',
            'NENE2_LOCAL_JWT_SECRET' => 'dev-secret',
            'NENE2_LOCAL_MCP_ORG' => 'not-a-number',
        ]);

        self::assertIsArray($plan->mintClaims);
        self::assertSame(1, $plan->mintClaims['org']);
    }
}
