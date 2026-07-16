# ADR 0021: Local MCP Server as a NENE2 Consumer (dev-only, read-only default)

## Status

proposed

> **Fleet reference proposal.** NeNe Invoice is the first sibling to wire up a
> runtime local MCP server (records and clear ship the catalog only). This ADR is
> written as a **reference for the fleet**: the auth boundary decided here becomes
> a fleet default, so it must be reviewed at the standards checkpoint (統合リナ)
> before records / clear copy it. Sequenced as *implement-first, then
> standardise* — the same shape as the codegen regen-diff (ADR-less #668): the
> real claim shape and guard boundaries are more accurate than an abstract
> fleet-wide decision made up front.

## Context

NeNe Invoice already ships a **static MCP tool catalog** at `docs/mcp/tools.json`,
generated from the OpenAPI spec by `tools/generate-mcp-tools.php` and validated by
`composer mcp` (NENE2 `validate-mcp-tools.php`). Today it is a **manifest with no
runtime** — 10 `read` tools + 5 `admin` tools, no `write`/`destructive`.

NENE2 (v1.11.0) provides a complete local MCP server:

- `Nene2\Mcp\LocalMcpServer` — JSON-RPC 2.0 / MCP over stdio (`initialize`,
  `tools/list`, `tools/call`), proxying each call to the HTTP API.
- `Nene2\Mcp\LocalMcpToolCatalog` — reads `docs/mcp/tools.json`.
- `Nene2\Mcp\NativeLocalMcpHttpClient` — HTTP client that adds
  `Authorization: Bearer <token>` when a token is present.

The consumer surface is thin — a runner wires these together and loops on stdio:

```php
$server = new LocalMcpServer(
    new LocalMcpToolCatalog($root . '/docs/mcp/tools.json'),
    new NativeLocalMcpHttpClient($bearerToken),   // ← the only real decision
    $apiBaseUrl,
    $auditLogger,
);
```

**The only design variable is `$bearerToken`.** Everything else is boilerplate
shared with the framework. This ADR exists to decide *how the local MCP server
authenticates to Invoice's multi-tenant API* and *what it is allowed to expose*.

### What makes Invoice different from the NENE2 example

- **Every useful tool needs a bearer token.** NENE2's example API answers `read`
  tools publicly, so its runner works token-less. Invoice's read tools hit
  `/admin/*`, which passes through `OrgGuardMiddleware` + capability checks —
  **without a token they return 401** (only `getHealth` is public). So the
  MCP-layer rule "read tools need no auth" does *not* mean Invoice's read tools
  work anonymously; it only means the MCP layer won't *block* them before the
  HTTP call.
- **Tokens are tenant-scoped.** Invoice login tokens carry claims
  `{sub, role, org}` (`org` = `organization_id`; `superadmin` bypasses the org
  check in `OrgGuardMiddleware`). A token minted for the MCP server therefore
  picks an organization and a role, and those decide what the tools can see.
- **`admin`-safety tools are cross-tenant / oversight.** `listOrganizations` and
  `getOrganizationById` are superadmin-only cross-tenant reads; `listUsers` /
  `getUserById` / `listAuditLogs` are org-admin oversight. Exposing them widens
  blast radius beyond a single tenant's business data.

### Transport reality (correcting one assumption)

The MCP server speaks **stdio JSON-RPC (STDIN/STDOUT) — it binds no network
port at all**, which is strictly safer than a `127.0.0.1`-bound HTTP listener
(unlike chat-relay, which is an HTTP server on 127.0.0.1). The reachability
concern therefore lands not on the MCP server itself but on **the API base URL it
targets**: that URL must be local and must never point at a remote/production
API.

## Decision

Wire a **dev-only, read-only-by-default** local MCP server as a NENE2 consumer.

### 1. Authentication — option (C): pre-issued by default, dev-secret fallback opt-in

`$bearerToken` is obtained as follows:

1. **Default — pre-issued bearer via env** (`NENE2_LOCAL_MCP_BEARER`). The operator
   issues a token through the *existing* paths (login, or
   `tools/issue-service-token.php`) and passes it in. **No token-minting logic
   lives in the runner** — it does not derive privilege from a secret, and the
   token is naturally scoped to one org + one role by whoever issued it.
2. **Fallback — mint from the local JWT secret**, used **only when
   `NENE2_ALLOW_DEV_SECRET=1`** (an env Invoice already defines for exactly this
   "dev secret is explicit opt-in" purpose). When enabled, the runner mints
   `{sub, role, org}` from `NENE2_LOCAL_JWT_SECRET`, with the org taken from
   `NENE2_LOCAL_MCP_ORG` and a **non-superadmin** role by default.
3. **Neither set → no token.** Only `getHealth` works; every `/admin/*` tool
   returns 401. This is the fail-closed default: the tool does not invent access.

This mirrors the fleet's 2026-07-16 working rule — *the tool does not invent
names / dev-secret privilege is explicit opt-in* — rather than silently minting a
powerful token from a secret that merely happens to be present.

### 2. Exposure — read-only by default, admin behind an explicit opt-in

- **Default: the 10 `read` tools only.** No mutation is reachable, so the server
  is out of scope for accounting/tax compliance (`accounting-compliance.md`).
- **`admin` tools (the 5 cross-tenant / oversight reads) are filtered out unless
  `NENE2_LOCAL_MCP_INCLUDE_ADMIN=1`.** When enabled, they still require a token
  whose role actually satisfies the endpoint (superadmin for org reads), so the
  opt-in flag widens *exposure* but not *authorization* — the HTTP/JWT boundary
  remains the real gate.
- **Audit when admin is enabled.** State-changing calls are already audited by
  `LocalMcpServer` (to STDERR, never args/secrets). Admin reads are not
  state-changing, but enabling `NENE2_LOCAL_MCP_INCLUDE_ADMIN` is itself a
  privileged posture: the runner logs (to STDERR) that admin tools were exposed
  and, for each admin `tools/call`, the tool name + downstream request id for
  correlation — so an operator can answer "who saw what" from the MCP process log
  together with the API's own audit trail.

### 3. Dev-only posture (hard constraints)

- **Development convenience, not a production backdoor** — inherited verbatim from
  NENE2's local-MCP guidance.
- **Not shipped in the production artifact and never auto-started.** The runner is
  a `tools/` script invoked by hand (or via a `composer` script); no middleware,
  no route, nothing the app boots. `tools/build-release.sh` must not include it in
  the release ZIP.
- **No external reachability** — stdio has no listener; `NENE2_LOCAL_API_BASE_URL`
  must be local (`http://localhost:8510` host-run, or `http://app` inside
  Compose) and must not be pointed at a remote/production API.

## Rationale

- **Telemetry-free trust pillar — no conflict.** A core promise of the OSS is that
  self-hosted installs carry **zero telemetry** (nothing phones home). A dev-only
  stdio tool that is absent from the release artifact and never auto-starts does
  not touch that promise: an operator's production install ships without it. This
  is worth stating because records / clear will copy this pattern, and the same
  guarantee must survive each copy.
- **Implement-first, then standardise.** Deciding the fleet auth boundary from
  Invoice's *real* `{sub, role, org}` claims and *real* OrgGuard/capability
  behaviour is more accurate than an abstract up-front fleet rule — the same
  reasoning as the codegen regen-diff. The cost is that 統合リナ must review this
  ADR before records / clear copy it, since the auth boundary becomes a fleet
  default.
- **W2 lesson — unattended access paths bypass controls.** A local MCP server is
  an unattended-agent access path. Read-only default + fail-closed token + no
  auto-start + not-in-artifact keeps the path from becoming a control bypass.

## Consequences

- New env keys (documented, values never committed): `NENE2_LOCAL_MCP_BEARER`,
  `NENE2_LOCAL_MCP_ORG`, `NENE2_LOCAL_MCP_INCLUDE_ADMIN`. Reuses existing
  `NENE2_LOCAL_JWT_SECRET`, `NENE2_ALLOW_DEV_SECRET`, `NENE2_LOCAL_API_BASE_URL`.
- Sequenced follow-up (not this ADR): the runner `tools/local-mcp-server.php`,
  `.env.example` documentation, an operator doc, and a smoke check
  (`tools/mcp-smoke.sh` equivalent on port 8510) with a test.
- `write` / `destructive` tool exposure stays **out of scope** — a later decision,
  including whether any of it touches the tax-adviser gate.
- records / clear can adopt this once 統合リナ reviews it; the auth boundary here
  is the proposed fleet default.
