# ADR 0015: Location-Independent Install via Runtime Base-Path Detection

## Status

proposed

## Context

The Tier A audience (ADR 0003) installs on Japanese shared hosting (heteml,
sakura, etc.) with **no shell, no root, no CLI**, and limited technical literacy
— for many, "subdomain" is an unfamiliar concept. For that audience the minimum
viable deployment story is: **unzip anywhere, open `install.php`, done** — the
operator must never have to reason about *where* the app sits in the URL space.

In practice the document root is rarely free: it usually already hosts WordPress
or the company's own static site. So the realistic placements are:

- **document root** — `https://example.com/` (dedicated host),
- **subdomain** — `https://invoice.example.com/` (own document root), and
- **subdirectory** — `https://example.com/invoice/` (alongside the existing
  site; the lowest-friction "just drop a folder" option, and the model a
  **NeNeSuite** bundle would use: `/NeNeSuite/invoice`, `/NeNeSuite/records`).

ADR 0003 implicitly assumes the app **is** the document root ("served from a
document root"). Today the codebase hard-codes that assumption in several places,
and the SPA-serving model is not actually coherent even for a subdirectory under
the document root:

- The SPA router (`createBrowserRouter([{ path: '/' … }])`) has **no
  `basename`** — it assumes mount at `/`, yet the build output lives in
  `public_html/admin/`.
- There is **no SPA deep-link fallback** (`admin/.htaccess` absent; the root
  `.htaccess` rewrites everything non-file to `index.php`), so a deep link or F5
  on an admin route does not return the SPA shell.
- The frontend API client (`shared/api/client.ts`) calls **absolute paths**
  (`/auth/login`, `/admin/me`, `/auth/refresh`).
- The PHP router matches `getUri()->getPath()` **with no base-prefix stripping**.
- The ADR 0014 session cookies hard-code `Path=/auth` (refresh) and `Path=/`
  (CSRF), which break / over-scope under a subdirectory.

Constraints that bound the decision:

- **Zero placement awareness for the operator.** No "what's your base path?"
  question; no manual config in the common case.
- **One artifact, any location.** The same release ZIP must work at `/`,
  `/invoice/`, or a subdomain root **without rebuilding** the frontend. (Vite's
  `base` is a *build-time* constant, so it cannot encode an install-time path.)
- **No shell / no CLI / Apache shared hosting** (ADR 0003).
- **Do not regress ADR 0014 / security posture.** Fail-closed auth, the
  in-memory access token, the httpOnly refresh cookie, and CSRF double-submit
  must keep working; cookie scoping must stay correct (not broader than the app).

### Options considered

1. **Build-time base (`vite build --base=/invoice/`) per install.** Rejected:
   requires the operator to rebuild the SPA for their path — impossible for the
   no-CLI audience, and breaks "one ZIP, any location."
2. **Document-root / subdomain only (status quo intent).** Rejected: forces the
   "subdomain is foreign" audience onto the one placement they find hardest, and
   collides with the existing site at the document root.
3. **Operator types the base path into the installer.** Rejected: it is exactly
   the placement awareness we are trying to eliminate, and a mistyped base is a
   confusing hard-to-diagnose failure.
4. **Runtime base-path auto-detection.** The server already knows where it is
   (`SCRIPT_NAME` = the URL to `index.php`); detect the base at request time,
   strip it before routing, inject it into the SPA shell, and scope cookies to
   it. Root / subdomain / subdirectory all collapse to "base is `/` vs
   `/invoice`." **Selected.**

## Decision

Adopt **location-independent installation via runtime base-path detection**. The
install location is discovered at runtime, never configured by the operator in
the common case, and one release artifact runs at any path.

- **Detection.** The application derives its **base path** at request time from
  `SCRIPT_NAME` (`dirname` of the front controller's URL → `/invoice` or `/`).
  An optional `.env` `APP_BASE_PATH` overrides detection for edge cases (reverse
  proxies, atypical rewrites). Detection is the default; override is escape hatch.
- **Front controller serves the SPA shell with the base injected.** Non-API
  requests are served the admin SPA shell (`admin/index.html`) **through
  `index.php`**, which injects the detected base as `window.__APP_BASE__` (and
  emits the same security headers as the static path via
  `SecurityHeadersMiddleware`). Hashed assets under `admin/assets/*` remain
  **statically served real files** (they are base-relative already — Vite
  `base: './'`). This single move also fixes SPA deep-links / F5.
- **Backend strips the base before routing.** The base prefix is removed from the
  request path so existing route definitions (`/auth/*`, `/admin/*`, `/api/*`,
  `/health`) match unchanged regardless of placement.
- **Frontend consumes the injected base.** The SPA reads `window.__APP_BASE__`
  and applies it as the React Router `basename` **and** as the prefix for every
  API call in `shared/api/client.ts`. No absolute-path assumptions remain.
- **Cookies are scoped to the detected base.** The ADR 0014 cookies become
  base-relative: refresh cookie `Path=<base>/auth`, CSRF cookie `Path=<base>/`.
  This both fixes subdirectory breakage and **tightens** scope so the CSRF cookie
  no longer leaks to a sibling site sharing the domain at `/`.
- **The installer shows, never asks.** `install.php` displays the detected
  install location ("検出した設置先: `/invoice/`") for transparency and writes any
  needed value to `.env`; it does not prompt for a base path.

This decision records the **direction and constraints**. The concrete injection
contract (`window.__APP_BASE__` vs `<base href>`), the base-strip middleware, and
the cookie-path wiring are settled in implementation, gated on this ADR being
`accepted`.

## Consequences

**Benefits**

- True "unzip anywhere → `install.php` → done" for the target audience; root,
  subdomain, and subdirectory all work with one artifact and no rebuild.
- Unlocks the **NeNeSuite** subdirectory packaging (`/NeNeSuite/invoice`, …).
- Fixes latent gaps that exist even today (SPA `basename`, deep-link/F5
  fallback), not just the subdirectory case.
- Tightens cookie scoping under shared domains (CSRF cookie no longer at `/`).

**Costs / risks**

- The SPA **HTML shell** is now served through PHP rather than purely statically
  (assets stay static). HTML is tiny and headers are reproduced in PHP, so the
  cost is negligible — but it is a change to the serving model and must be
  re-checked against the front-end security assessment.
- Base detection must be **robust** against shared-hosting quirks (`SCRIPT_NAME`
  vs `PATH_INFO`, trailing slashes, case); a wrong base is a confusing failure,
  so the `.env` override and clear installer display are part of the safety net.
- Touches the just-shipped ADR 0014 auth path (cookie `Path`), so it needs a
  security pass alongside #465.

**Follow-up** (separate issues, gated on this ADR `accepted`)

- Backend: base detection (`SCRIPT_NAME` + `APP_BASE_PATH` override), base-strip
  before routing, front-controller SPA-shell serving with base injection +
  security headers, base-relative cookie `Path`.
- Frontend: read `window.__APP_BASE__` → React Router `basename` + API-client
  prefix; remove absolute-path assumptions; relative asset serving verification.
- Installer: detect + display the install location; persist any required value.
- Docs: update ADR 0003 (document-root assumption), the install guide, and the
  ADR 0014 cookie-path note.
- Security review of the SPA-via-PHP serving and base-relative cookie scoping
  (coordinate with #465).

## Related

- Issue: `#472`
- PR: `#000`
- Updates: ADR 0003 (dual deployment tiers — document-root assumption)
- Related: ADR 0014 (session cookies — `Path` becomes base-relative)
- Supersedes: none
- Superseded by: none
