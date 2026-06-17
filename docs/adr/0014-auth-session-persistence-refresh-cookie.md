# ADR 0014: Silent Re-authentication via httpOnly Refresh Cookie

## Status

accepted

## Context

The admin UI holds its bearer (access) token **in memory only**
(`frontend/src/shared/api/client.ts`), and the auth shell
(`frontend/src/app/auth-gate.tsx`) is fail-closed: no token in memory → render
the login screen. This was a deliberate XSS-minimizing choice — there is no
JS-readable storage (`localStorage` / non-`httpOnly` cookie) from which injected
script could exfiltrate a credential.

The cost is a poor session UX: **any full page reload (F5) discards the
in-memory token and bounces the user straight to login.** For an invoicing
product whose operators keep tabs open all day and reload frequently, this is
far more aggressive than mainstream SaaS, where a reload silently restores the
session. It also degrades development: when Vite HMR escalates to a full reload,
the session is lost.

Both the code comment in `client.ts` and `docs/development/adr.md` state that a
`localStorage` / cookie-backed session **requires an ADR**. This is that ADR.

Constraints that bound the decision:

- **Do not regress XSS posture.** The current strongest property is "no
  JS-readable long-lived credential." Any solution must preserve it.
- **Fail-closed auth must remain** (memory: security posture). No token → login,
  and a server-rejected session must end the session.
- **Multi-tenant** (ADR 0006): refresh must re-mint a token scoped to the same
  `organization_id`; cross-tenant escalation on refresh is unacceptable.
- The auth foundation is inherited from NENE2; a new `/auth/refresh` endpoint is
  a backend change to that boundary.

### Options considered

1. **Keep in-memory-only (status quo).** Best XSS posture, worst UX. Rejected:
   the UX cost is disproportionate for this product.
2. **Persist the access token in `localStorage`.** Survives reload, but the
   token becomes JS-readable → directly regresses XSS posture. Rejected.
3. **`sessionStorage` access token.** Survives reload within the *same tab*,
   cleared on tab close. Still JS-readable (weaker XSS posture) and does not help
   new tabs. Rejected as the primary mechanism; noted as a lighter fallback.
3a. **Operator-selectable session mode (install-time and/or settings-screen
   toggle).** Rejected as a *parallel two-mode* design; see "Configurability —
   no security-mode toggle". The session mechanism is a single secure default.
4. **Short-lived access token in memory + long-lived refresh token in an
   `httpOnly`, `Secure`, `SameSite` cookie; silent `/auth/refresh` on app
   start.** Access token stays non-persisted (XSS posture preserved); refresh
   token is not JS-readable (XSS cannot steal it); reload no longer bounces to
   login. This is the mainstream secure pattern. **Selected.**

## Decision

Adopt **silent re-authentication via an `httpOnly` refresh cookie**:

- **Access token**: remains short-lived and **in memory only**. No change to how
  the API client carries it (`Authorization: Bearer`). It is never written to
  `localStorage` / `sessionStorage` / a JS-readable cookie.
- **Refresh token**: issued by the backend on login as an `httpOnly`, `Secure`,
  `SameSite` cookie, scoped to the API path. It is never readable by JS.
- **New backend endpoint `POST /auth/refresh`**: validates the refresh cookie
  and returns a fresh access token for the **same user and `organization_id`**.
- **App start / reload flow**: before showing login, the auth shell attempts one
  silent `/auth/refresh`. Success → seed the in-memory access token and render
  the app. Failure (no/expired/invalid cookie) → fail-closed to login, exactly
  as today. The user is no longer bounced to login on a routine reload.
- **Refresh-token rotation with reuse detection**: each refresh rotates the
  token; presenting an already-used token invalidates the session family
  (replay defense).
- **Logout / revocation is server-side**: logout (and any session kill) MUST
  invalidate the refresh token server-side and clear the cookie. Clearing the
  in-memory access token alone is not sufficient.
- **CSRF defense is mandatory** because the refresh now rides a cookie. Default
  to `SameSite=Strict` on the refresh cookie **plus** a double-submit CSRF token
  on `/auth/refresh` and other state-changing routes; the concrete scheme is
  settled in implementation but CSRF coverage is non-negotiable.

This decision records the **direction and constraints**. The backend
`/auth/refresh` implementation and the frontend silent-refresh wiring are
deferred to follow-up issues gated on this ADR being `accepted`.

## Configurability — no security-mode toggle

A natural question for a self-hosted product is whether the session model should
be **operator-selectable** (e.g. "strict in-memory-only" vs "persistent
session"), exposed at install time and/or in the settings screen. The decision
is **no security-mode toggle**, for these reasons:

- **The premise of this ADR is that the tradeoff is removed**, not balanced: the
  refresh-cookie model keeps the access token non-persisted and the refresh
  token non-JS-readable, so it does **not** meaningfully regress XSS posture.
  Offering "strict mode" as an alternative would mainly offer *worse UX for no
  real security gain*, and would signal that the secure default is not
  trustworthy. So the session **mechanism is a single secure default**.
- **A runtime, admin-flippable toggle in the settings UI is rejected outright.**
  Auth/session hardening behind a UI switch is a classic misconfiguration
  vector; under multi-tenancy (ADR 0006) it also fragments session-security
  policy per organization, muddying operation and audit. Above all it forks the
  most security-sensitive code path in two — doubling test, review, and
  fail-closed-maintenance cost.
- **If — and only if — genuine demand for a paranoid "no persistence" mode is
  proven**, expose it as a **deploy-time hardening flag** (an env var, immutable
  at runtime, documented as "high-security deployments only"), **never** as a
  settings-screen control. This is deferred and out of scope until such demand
  exists; building two auth modes speculatively is rejected.

What **does** belong to operators/users is the convenience↔security dial that
does not fork the auth path:

- **"Keep me signed in" (remember me)** → maps to refresh-token lifetime
  (short, session-bound cookie when unchecked; longer-lived rotated cookie when
  checked).
- **Idle / absolute session timeout** → auto-logout policy.

Note: the login form **already renders a "ログイン状態を保持" checkbox**
(`frontend/src/features/sign-in/ui/SignInForm.tsx`), but it is currently a bare
`<input type="checkbox" />` with no state binding — **purely decorative**. It is
the natural anchor for the remember-me behavior once `/auth/refresh` exists (the
adjacent "forgot password" link is likewise a non-wired placeholder, out of
scope here).

## Consequences

**Benefits**

- Reload no longer logs the operator out — UX matches mainstream SaaS.
- XSS posture is effectively preserved: the access token stays non-persisted and
  the refresh token is not JS-readable.
- Fail-closed behavior is retained (silent refresh failure → login).
- Better dev loop (HMR full reloads survive).

**Costs / risks**

- Introduces cookie-based auth → **CSRF surface**, which must be defended
  (SameSite + double-submit token). This is new attack surface to review.
- Requires server-side refresh-token storage/rotation and revocation — more
  moving parts than a stateless in-memory token.
- Touches the NENE2-inherited auth boundary; needs a security pass before
  release.

**Follow-up**

- Backend: `POST /auth/refresh`, refresh-token issuance/rotation/revocation,
  CSRF protection. (separate issue, gated on this ADR `accepted`)
- Frontend: silent refresh on app start in the auth shell; wire logout to
  server-side revocation. (separate issue)
- Frontend: wire the existing decorative "ログイン状態を保持" checkbox to
  remember-me (refresh-token lifetime); add idle/absolute timeout policy.
  (separate issue)
- Register any new identifiers (endpoint `operationId`, Problem Details slugs,
  fields) in `docs/explanation/terminology.md` in the implementing PR.
- Security review of the CSRF scheme and rotation/reuse-detection before release.

## Related

- Issue: `#458` (ADR); implementation `#462` (backend), `#463` (frontend), `#464` (remember-me), `#465` (security review)
- PR: `#459` (proposed), `#461` (accepted); implementation `#466` (backend), `#467` (frontend)
- Related: ADR 0006 (multi-tenancy and roles — refresh must stay tenant-scoped)
- Supersedes: none
- Superseded by: none
