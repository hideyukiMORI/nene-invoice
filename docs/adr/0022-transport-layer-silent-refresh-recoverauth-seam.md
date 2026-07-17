# ADR 0022: Transport-Layer Silent Refresh via the nene2-js `recoverAuth` Seam

## Status

accepted

## Context

ADR 0014 established the session direction: a short-lived **access token in
memory only** plus a silent `/auth/refresh` on app start backed by an
`httpOnly` refresh cookie. The admin UI implemented that with a **hand-rolled
`apiClient`** (`frontend/src/shared/api/client.ts`) that owned its own `fetch`
wrapper, `401`-triggered refresh replay, single-flight de-duplication, token
store, `X-Auth` mirroring, and blob/raw handling.

Two forces made that hand-rolled transport a liability:

1. **Fleet duplication.** Sibling products (payout, and others migrating to the
   shared `@hideyukimori/nene2-client` transport) re-implement the same
   401-refresh/single-flight/error-mapping logic. NENE2's transport grew a
   dedicated **`recoverAuth` seam** (published in `@hideyukimori/nene2-client`
   **1.2.0**; 1.1.0 had the transport but not the seam) so consumers can plug in
   their silent-refresh probe without re-owning the plumbing. Keeping invoice on
   a bespoke client means its auth-critical code path diverges from the fleet
   and is reviewed/hardened alone.

2. **A latent multi-tenant bug in path mode (fleet issue #38).** Under
   path-based tenancy (`/{slug}/…` — the demo and the future 型B MSP/税理士
   "おまかせ運用" SKU), the backend `RefreshHandler` rotated the `ni_refresh`
   cookie at the **install base** (slug already stripped by `OrgResolver`),
   landing it at `/{base}/auth` instead of `/{base}/{slug}`. The next
   `/{slug}/auth/refresh` therefore did **not** carry the rotated cookie →
   reuse-detection burned the token family → hard logout. Silent refresh in path
   mode worked exactly **once**. The demo tolerated this via a fail-closed
   one-shot (case A); it was unsafe to enable a persistent rotating refresh in
   path mode until the cookie scope was fixed.

W2b is the workstream that migrates invoice's `apiClient` onto the shared
transport seam. Its core landed in **#684 / #685** as a **behavior-preserving
refactor** (all 10 `client.test.ts` behavior tests unchanged and green;
`tsc -b` green with the `apiClient` verb signature preserved, so the ~40
consuming files and `entities/auth` were untouched). What remained was (a) the
backend root-fix for #38 and (b) **this ADR** recording the decision, since
`docs/development/adr.md` requires an ADR for changes to the inherited auth
boundary.

### Options considered

1. **Keep the hand-rolled `apiClient` (status quo).** Zero migration risk, but
   perpetuates fleet duplication of the most security-sensitive path and leaves
   invoice's silent-refresh logic diverging from the shared, separately-reviewed
   transport. Rejected.

2. **方針A — migrate now and immediately standardize the seam fleet-wide as the
   canonical auth transport.** Attractive for consistency, but it couples
   invoice's release to a fleet-wide standardization decision and to every
   consumer's readiness. It also front-runs the evidence: the seam should prove
   itself behavior-preserving in a real consumer before being ratified as the
   standard. Rejected as premature.

3. **方針B — migrate invoice onto the `recoverAuth` seam behavior-preservingly,
   record the intent to standardize later as a follow-up (not a precondition),
   and gate the risky path-mode activation behind an explicit promotion gate.**
   **Selected** (施主裁定 2026-07-14).

## Decision

Adopt **方針B**: invoice consumes the `@hideyukimori/nene2-client` (>= 1.2.0)
transport's **`recoverAuth` seam** as its silent-refresh mechanism, preserving
the ADR 0014 session behavior byte-for-byte, and enabling the seam **per tenancy
mode behind a promotion gate**.

- **Behavior preserved (ADR 0014 intact).** The access token stays **in memory
  only** (the in-memory store becomes a `TokenStore` adapter; `sessionStorage`
  is explicitly out of scope — it regresses XSS posture and needs its own ADR).
  App-start probe = `refreshSession()` → `transport.recover()`, sharing the
  transport's single-flight so rotation-reuse defense is unchanged. Error shape
  is preserved (`Nene2ClientError` → `AppError.fromProblem`, `.slug`/`.status`
  intact, so the 3-type error display does not change). Fail-closed on refresh
  failure remains.

- **Per-mode promotion gate (統合リナ裁定 2026-07-16).** Whether `recoverAuth`
  is handed to the transport depends on the resolved tenancy mode, detected
  purely from the `app-base` meta vs the install `<base href>`
  (`deriveInstallBase` / `isPathTenancy`, pure + tested):
  - **single / host mode** → **pass `recoverAuth`** (there is no slug to strip,
    so #38 does not apply; the current silent refresh is retained). **Enabled.**
  - **path mode** (demo / 型B) → **do not pass `recoverAuth`** (fail-closed
    one-shot) **until #38 is root-fixed**, because a rotating refresh in path
    mode would burn the token family. The production host `invoice.ayane.co.jp`
    is a path-mode demo, so this stays **off** there for now; a future single-mode
    deployment auto-enables it (future-proof).

- **Promotion gate = W2b completion AND the #38 root-fix design.** These are now
  both satisfied at the backend/frontend-mechanism layer:
  - W2b core migration: **#684 / #685** (behavior-preserving, merged).
  - #38 root fix: **#693 / #694** — the cookie base is unified on
    `appBaseFromRequest()` so path-mode rotation re-issues `ni_refresh` at
    `/{base}/{slug}`; non-path modes fall back to the install base (byte-equal to
    before). Backend + tests only (`composer check` green, 1007 tests). That
    commit records itself as "the sole precondition for the W2b promotion gate
    (enabling `recoverAuth` in path mode)".
  - **Remaining before path-mode is switched on in production**: flip the
    frontend per-mode gate to pass `recoverAuth` in path mode **and** deploy —
    both are an **explicit owner (施主) GO**, not an automatic consequence of
    this ADR. This ADR authorizes the direction; it does not itself turn path-mode
    silent refresh on.

- **Future standardization is intent, not a precondition.** Making this seam the
  canonical fleet auth transport is recorded as desirable follow-up (payout
  already shares the same pattern), but invoice's adoption does **not** wait on
  that ratification.

## Consequences

**Benefits**

- Invoice's auth-critical transport (401-refresh, single-flight, error mapping,
  `X-Auth` mirror, blob/raw) is now **owned by the shared, separately-reviewed
  nene2-js transport** instead of a bespoke client — less duplicated
  security-sensitive code across the fleet.
- ADR 0014's XSS posture and fail-closed behavior are **unchanged** (proven by
  the unchanged behavior tests in #685).
- The #38 path-mode family-burn bug is structurally removed at the backend
  (#693/#694), unblocking a future persistent silent refresh for the path-mode
  多tenant SKU.

**Costs / risks**

- The transport is now an external dependency pinned at `>= 1.2.0`; the seam's
  contract (`recoverAuth?` / `recover()`) must stay compatible across nene2-js
  bumps (verified today by installing 1.2.0 and cross-checking types).
- Two activation states (seam on/off) exist per tenancy mode. This is
  deliberately **not** a runtime toggle — it is derived from the deployment's
  tenancy shape — but it is a branch in the auth path that review must keep in
  mind.
- Turning path-mode silent refresh on is gated on an owner GO + deploy; until
  then path-mode users keep the one-shot fail-closed session (a UX cost accepted
  for safety).

**Follow-up**

- Frontend: flip the per-mode gate to pass `recoverAuth` in path mode after the
  owner GO, then deploy; verify no family-burn on `invoice.ayane.co.jp`.
- Fleet: consider ratifying the `recoverAuth` seam as the canonical auth
  transport once a second/third consumer (payout, …) has run it in production.
- Register any new identifiers introduced by the eventual path-mode enablement
  in `docs/explanation/terminology.md` in the implementing PR.

## Related

- Issue: `#701` (this ADR); fleet bug `#38` (path-mode refresh-cookie
  family-burn, `_work/issues.md`)
- PR: W2b core `#685` (Closes `#684`); #38 root fix `#694` (merges `#693`)
- Related: ADR 0014 (silent re-authentication via httpOnly refresh cookie — this
  ADR keeps its behavior and moves the mechanism onto the shared seam), ADR 0006
  (multi-tenancy — refresh must stay tenant/`organization_id`-scoped)
- Supersedes: none
- Superseded by: none
