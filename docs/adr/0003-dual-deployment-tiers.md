# ADR 0003: Dual Deployment — Tier A Shared Hosting and Tier B Docker

## Status

accepted

## Context

NeNe Invoice is self-hosted OSS for Japan SMBs. Its primary audience already pays
for **shared hosting** (Xserver, Sakura, Lolipop, etc.) and is often non-technical —
general-affairs or accounting staff, not engineers (see `product-vision.md`). They
will not run Docker, a CLI, or a long-running process, and they do not have root.
A second audience — developers and VPS operators — wants a reproducible container
setup for local development and production.

Shared hosting imposes hard constraints:

- PHP runs per-request (FPM/CGI); **no long-running daemons, queues, or workers**.
- **No root, no shell access** assumed; no custom system packages or PHP extensions
  beyond the common set (PDO, pdo_mysql, mbstring, json, openssl).
- The application is served from a **document root** (e.g. `public_html/`); only that
  directory is web-exposed.
- **No Composer at runtime** — dependencies must be shipped, not installed on the host.
- **MySQL** is the available database; migrations cannot rely on a CLI being present.

Alternatives considered:

1. **Docker-only** — rejected; excludes the majority (shared-hosting) audience, which
   is the product's reason to exist.
2. **Shared-hosting-only** — rejected; poor developer ergonomics and no reproducible
   production story for Tier B operators.
3. **Two divergent codebases** — rejected; doubles maintenance and invites compliance
   drift between billing implementations.
4. **One codebase, two deployment tiers** (chosen).

## Decision

Ship **one codebase and one runtime** (NENE2 consumer, single `public_html/index.php`
entrypoint, one MySQL schema via Phinx migrations + SQLite schema snapshots) packaged
two ways:

**Tier A — Shared hosting (primary).**

- Distributed as a **release ZIP** that includes `vendor/` and built admin assets
  (no Composer or Node on the host).
- A **web installer** collects MySQL credentials, the initial admin user, and company
  information; writes `.env`; and applies the database schema **without a CLI** (the
  installer runs the same migration set Phinx applies on Tier B).
- **Same-origin admin**: PHP serves both the API and the prebuilt admin UI from the
  document root, avoiding cross-origin setup the audience cannot perform.
- Request/response only — no feature may require a daemon, queue, or cron to function
  (scheduled work, if ever needed, degrades gracefully or is Tier B only).

**Tier B — Docker / VPS (secondary).**

- **Docker Compose** (app + MySQL) for local development and production.
- Migrations via `composer migrations:migrate` (Phinx CLI); assets built from source.
- **Identical API, admin UI, schema, and configuration surface** as Tier A.

**Shared invariants (both tiers).**

- MySQL in production on both tiers; SQLite is for tests only.
- Configuration is environment-based (`.env`); no tier-specific code paths in domain logic.
- The PHP floor is the project's required version (8.4); hosts below it are unsupported.

## Consequences

**Benefits.**

- Reaches the shared-hosting majority *and* gives developers a reproducible setup.
- One source of truth for billing/compliance logic — no divergence between tiers.

**Costs / constraints.**

- No feature may depend on root, background processes, cron, or uncommon PHP
  extensions, or it breaks Tier A. Such features are Tier B-only and must be optional.
- The release ZIP needs a build step that vendors dependencies and bundles admin assets.
- The web installer must apply migrations safely and idempotently outside the CLI,
  staying in lockstep with the Phinx migration set.
- PHP 8.4 availability on target shared hosts is a real adoption risk; the operator
  guide must state the supported PHP/MySQL versions explicitly.

**Follow-up (Phase 3).**

- Web installer + release-ZIP build tooling; Japanese operator guide.
- Document supported PHP/MySQL versions and the shared invariants above.
- A migration-parity check so the installer and Phinx never diverge.

## Related

- Issue: `#7`
- PR: `#82`
- Builds on: ADR 0001 (inherit NENE2 governance), ADR 0002 (separate from siblings)
- See: `docs/explanation/product-vision.md` (audiences), `docs/roadmap.md` (Phase 3),
  `docs/explanation/glossary.md` (Tier A / Tier B)
- Updated by: ADR 0015 (location-independent install — relaxes the "served from
  the document root" assumption via runtime base-path detection)
- Supersedes: none
- Superseded by: none
