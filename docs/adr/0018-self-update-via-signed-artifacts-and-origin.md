# ADR 0018: Self-Update via Signed Release Artifacts (Tier A) and Private Origin Boundary

## Status

accepted (2026-06-19 — direction and boundary decision. The security / failure-mode
review of the in-process Tier A updater (signature verification, maintenance gate,
partial-write handling) is an implementation-time follow-up, not a precondition of
this decision. Announcements + free-tier house ads are deferred to ADR 0019.)

## Context

NeNe Invoice ships as self-hosted OSS (MIT) to two audiences (ADR 0003): the
**Tier A** shared-hosting majority (heteml, sakura, Xserver, lolipop — **no shell,
no git, no Composer, no CLI, no cron, no root**, per-request PHP) and the **Tier B**
Docker/VPS minority. Today the product has **no version identity and no update
path**: `frontend/package.json` is `0.1.0`, there is no `VERSION`, no changelog, and
no mechanism to learn that a newer release exists or to apply it. Operators must
re-run the manual install flow to upgrade, which the Tier A audience cannot reliably
do.

We want three vendor-facing capabilities, all of which need an authoritative source
the product can reach over HTTP:

1. **Updates** — learn that a newer official release exists and apply it.
2. **Announcements** — official notices to operators (later ADR).
3. **House ads** for the MIT free tier (later ADR).

This ADR settles **updates** and the **ownership boundary of the source** that all
three consume. Announcements and house ads are deferred to a sibling ADR (0019) but
share the boundary decided here.

### Constraints

- **Tier A forbids git, Composer, a CLI, cron, root, and long-running processes**
  (ADR 0003). Any update must run **in-process in PHP, per request**. The available
  PHP extensions include `openssl` (signature verification is feasible) and a way to
  fetch over HTTPS (`curl` and/or `allow_url_fopen`) — but **some locked-down hosts
  block outbound HTTPS entirely**, so auto-fetch cannot be the only path.
- **DB rollback is effectively impossible on Tier A** (no `mysqldump`, no Phinx
  `down` via CLI). Safety cannot depend on reverting the database.
- **Data sovereignty is the product's brand** ("請求は SaaS に預けない"). Anything that
  phones home must be **auditable in open source**, minimal, and carry no PII.
- **Accounting compliance is binding** (`docs/explanation/accounting-compliance.md`):
  issued-document immutability and numbering continuity must survive an update and
  any rollback.
- **Supply-chain criticality**: an updater lets the application overwrite its own
  code from a remote source. Unsigned or tampered artifacts must never be applied.
- **Sibling products integrate HTTP-only, no shared DB** (ADR 0002); standalone
  installs (not joined to a suite) must update on their own.

### Options considered for the source/ownership boundary

1. **Put the official management system in the public NeNeSuite repo (MIT).**
   Rejected: release **signing keys** cannot live in a public repo; ad targeting and
   announcement authoring are commercially sensitive vendor logic on a different
   cadence than OSS releases; ad/telemetry endpoints are privacy-critical and must
   not take community PRs.
2. **Put it all in one private vendor repo and have products depend on the suite.**
   Rejected: standalone (non-suite) installs would have no update/announcement path;
   couples a per-product baseline capability to suite membership.
3. **Split by layer: private Origin (vendor service) + public per-product client,
   suite as optional aggregator** (chosen).

### Options considered for the Tier A apply model

- **git/Composer/symlink-atomic-swap as baseline** — rejected: each assumes a
  capability absent on real Tier A hosts (no git, no Composer; docroot is fixed and
  `symlink()` is not universally usable).
- **Maintenance-gate + in-place overwrite + pre-apply backup** (chosen baseline);
  symlink swap kept only as a capable-host optimization.

## Decision

### A. Ownership boundary — private Origin, public client, optional suite aggregator

- **Origin (vendor-operated, private).** A vendor service — the "NeNe Official
  Origin" — owns the signed release registry, version manifests, and (later)
  announcements and house-ad inventory. It **holds the signing private key**. It is
  **not** part of the public MIT product/suite repos.
- **Client (in each product, MIT, public, auditable).** Each product ships a thin
  client that checks for updates, **verifies signatures with a bundled public key**,
  renders announcements/ads, and performs the self-update. What it sends is
  open-source and minimal (see §E).
- **Origin is independent of the suite.** A standalone install talks to the Origin
  directly. When joined to a suite (ADR 0012-family / federation), the suite **may
  aggregate/proxy** "N apps have updates," but membership is never required to
  update. This mirrors the federation asymmetry (local-first, suite optional) and
  the verify-only key posture (ADR 0012: siblings verify, the central party signs).

### B. Update artifact — reuse the release ZIP; the updater is the installer in update mode

- The update artifact is the **same signed release ZIP** as Tier A install (ADR
  0003): it bundles `vendor/` and built admin assets — no Composer/Node on the host.
- The updater **reuses the installer's in-process migration runner**. The installer
  applies a **full schema snapshot**; the updater applies **incremental** pending
  migrations only, tracked in the existing applied-migrations table.

### C. Apply model (Tier A baseline — minimum guarantee on every host)

1. **Maintenance gate.** The front controller checks a sentinel file first; while set,
   it serves an "updating" response so **no request executes half-updated code**.
2. **Pre-apply file backup.** Copy the current code tree aside (cheap, universal).
3. **In-place overwrite** from the verified ZIP.
4. **Idempotent incremental migrations** via the in-process runner.
5. **Clear the gate.** On any failure before the gate clears, the install is left on
   the prior code with the gate released to a safe state.

Symlink-swap (`releases/<v>/` extract → atomic `rename()` of a `current` pointer) is
an **optional optimization for capable hosts only**, never the baseline.

### D. Rollback safety — migration discipline, not DB revert

- **Migrations are additive / backward-compatible only (expand-contract).**
  Destructive changes are split across versions with a grace window. Therefore a
  failed update is recovered by **restoring the prior code** (step C2), which runs
  correctly against the already-migrated schema — **no DB rollback required**.
- This directly serves compliance: issued documents, `document_sequences`, and
  numbering continuity are never dropped or rewritten by an update or a rollback.

### E. Origin contract (HTTP) and acquisition

- **Version manifest.** The Origin publishes a **signed** manifest per product:
  `latest` version, `min upgrade-from`, ZIP URL, artifact SHA-256, signature, and a
  changelog reference.
- **Verification (mandatory, both acquisition paths).** The client verifies the
  manifest signature and the ZIP hash with `openssl` against the **bundled public
  key**. Mismatch or missing signature → **refuse to apply**. The signing key lives
  only at the Origin; the product holds the **public key only** (verify-only).
- **Acquisition — two paths, shared verification/apply.**
  - **Auto-fetch (default):** PHP fetches the signed ZIP over HTTPS.
  - **Manual upload (first-class fallback):** the operator downloads the ZIP and
    uploads it through the updater UI. Same signature check and apply path; this
    keeps hosts with blocked outbound HTTPS supported.
- **What the client sends is minimal and PII-free:** product id, current version, and
  an opaque install id — auditable in open source. No invoice/customer data.

### F. Version identity

Introduce a **canonical product version** (currently absent) as the single source the
update check compares against. The exact carrier (a `VERSION` file vs a build
constant) and the identifier names (e.g. an Origin base URL env, an update public-key
location, manifest field names) are settled at implementation and **registered in
`docs/explanation/terminology.md` in the implementing PR** (binding registry; this
ADR being `proposed` does not yet freeze identifiers).

## Consequences

**Benefits**

- The Tier A majority can update without shell/git/Composer/CLI — the audience the
  product exists for.
- One artifact and one apply path for install and update (installer ≙ updater);
  one signature scheme; one migration runner.
- Brand-consistent: the update/ad/announcement client is open and auditable; only
  the Origin (keys, ad/announcement authoring) is private.
- Standalone and suite installs both update; suite is an optional convenience, not a
  dependency.
- Expand-contract discipline gives a real recovery story on hosts where DB rollback
  is impossible, and protects compliance invariants.

**Costs / risks**

- **In-process overwrite is inherently delicate**; the maintenance gate + pre-apply
  backup mitigate but do not eliminate partial-write risk on pathological hosts.
  Needs a careful security and failure-mode review.
- **Outbound HTTPS is not guaranteed**; the manual-upload path must be a tested,
  first-class flow, not an afterthought.
- **Expand-contract is now a standing migration rule** — a review item for every
  schema change, not just update code.
- **MIT cannot enforce ad display or update behavior**; monetization (later ADR) must
  not rely on license enforcement.
- The Origin is **new vendor-operated infrastructure** (signing, key rotation,
  manifest hosting) that must exist before the client is useful.

**Follow-up** (separate issues)

- Product: canonical version carrier; update client (check → verify → acquire →
  apply); maintenance gate in the front controller; pre-apply backup; incremental
  in-process migration via the installer runner; manual-upload fallback UI.
- Migration policy: codify **expand-contract / additive-only** in
  `docs/development/backend-standards.md` and the DB review checklist.
- Origin (private): signed manifest endpoint, artifact signing + key rotation, public
  key bundling into products.
- Terminology: register update/Origin identifiers in the implementing PR.
- Sibling ADR **0019**: announcements feed + free-tier house ads (privacy invariants;
  no third-party ad networks / no behavioral targeting / no client tracking;
  MIT-non-enforceable monetization framing).
- Suite: optional update-aggregation client contract (suite repo is normative,
  mirroring the federation split).

## Related

- Issue: `#487`
- PR: `#488`
- Related: ADR 0003 (dual deployment / release ZIP), ADR 0002 (separate from
  siblings / HTTP-only), ADR 0006 (multi-tenancy), ADR 0015 (location-independent
  install), `docs/explanation/accounting-compliance.md` (immutability / numbering),
  Suite federation contract (verify-only key posture, suite-optional asymmetry)
- Supersedes: none
- Superseded by: none
