<?php

declare(strict_types=1);

namespace NeneInvoice\Mcp;

/**
 * Decides how the local MCP server obtains its bearer token (ADR 0021, option C).
 *
 * Pure decision from an environment map — no I/O, no clock — so it is unit
 * testable. The runner ({@see \tools/local-mcp-server.php}) executes the plan:
 * a pre-issued token is used verbatim; mint claims are handed to the app's
 * {@see \Nene2\Auth\TokenIssuerInterface} (which adds `iat`/`exp`), so that the
 * minted token is signed with the exact secret the running API verifies with.
 *
 * Fail-closed order:
 *   1. `NENE2_LOCAL_MCP_BEARER` set     → use it (no secret-derived privilege).
 *   2. else `NENE2_ALLOW_DEV_SECRET=1`
 *      and `NENE2_LOCAL_JWT_SECRET` set → mint `{sub, role, org}`.
 *   3. else                            → no token (only `getHealth` works).
 *
 * The minted role defaults to `admin` (non-superadmin) — the least-privilege role
 * that satisfies the full read-only tool set, since `getCompanySettings` needs
 * `manage_company_settings`, which member/viewer lack. Minting a `superadmin`
 * token is a deliberate dev-only escape hatch reachable ONLY through the triple
 * opt-in `NENE2_ALLOW_DEV_SECRET=1` + `NENE2_LOCAL_MCP_INCLUDE_ADMIN=1` +
 * `NENE2_LOCAL_MCP_ROLE=superadmin`; asking for it without enabling admin
 * exposure is refused loudly rather than silently downgraded.
 */
final readonly class LocalMcpAuthPlan
{
    private const DEFAULT_ROLE = 'admin';
    private const DEFAULT_ORG = 1;
    private const DEFAULT_SUB = 0;

    /**
     * @param array<string, mixed>|null $mintClaims `{sub, role, org}` to mint, or null
     */
    private function __construct(
        public ?string $preIssuedToken,
        public ?array $mintClaims,
    ) {
    }

    public function hasToken(): bool
    {
        return $this->preIssuedToken !== null || $this->mintClaims !== null;
    }

    /**
     * @param array<string, string|false|null> $env raw environment (e.g. getenv() map)
     *
     * @throws LocalMcpConfigException when a superadmin mint is requested without
     *                                 the admin-exposure opt-in
     */
    public static function fromEnv(array $env): self
    {
        $bearer = self::str($env, 'NENE2_LOCAL_MCP_BEARER');

        if ($bearer !== '') {
            return new self($bearer, null);
        }

        $allowDevSecret = self::str($env, 'NENE2_ALLOW_DEV_SECRET') === '1';
        $secret = self::str($env, 'NENE2_LOCAL_JWT_SECRET');

        if (!$allowDevSecret || $secret === '') {
            return new self(null, null);
        }

        $role = self::str($env, 'NENE2_LOCAL_MCP_ROLE');
        $role = $role === '' ? self::DEFAULT_ROLE : $role;

        if ($role === 'superadmin' && self::str($env, 'NENE2_LOCAL_MCP_INCLUDE_ADMIN') !== '1') {
            throw new LocalMcpConfigException(
                'Refusing to mint a superadmin MCP token: set NENE2_LOCAL_MCP_INCLUDE_ADMIN=1 to '
                . 'acknowledge cross-tenant admin exposure, or use a non-superadmin NENE2_LOCAL_MCP_ROLE.',
            );
        }

        $orgRaw = self::str($env, 'NENE2_LOCAL_MCP_ORG');
        $org = ctype_digit($orgRaw) ? (int) $orgRaw : self::DEFAULT_ORG;

        $subRaw = self::str($env, 'NENE2_LOCAL_MCP_SUB');
        $sub = ctype_digit($subRaw) ? (int) $subRaw : self::DEFAULT_SUB;

        return new self(null, ['sub' => $sub, 'role' => $role, 'org' => $org]);
    }

    /**
     * @param array<string, string|false|null> $env
     */
    private static function str(array $env, string $key): string
    {
        $value = $env[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
