# ADR 0016: Conform to the Suite Federation Participation Contract

## Status

accepted (2026-06-19 — conformance to NeNe Suite ADR 0012, accepted 2026-06-19;
inherits the 2026-05-31 公認会計士・税理士 sign-off and introduces no new compliance
obligation. The security review of asymmetric assertion verification / key handling is
an implementation-time follow-up per Suite ADR 0012, not a precondition of this
decision.)

## Context

NeNe Invoice must run two ways and convert between them without moving data
(operator requirement, decided cross-repo 2026-06-19):

1. **standalone** — installed on its own, local authentication, its own organization
   records.
2. **suite member** — joined to a NeNe Suite hub for SSO, a shared organization
   roster, and apex navigation.

The **normative contract is owned by NeNe Suite**, recorded in **its ADR 0012
"Federation Participation Contract" (accepted 2026-06-19)**. Sibling products conform
to it; they do not define it. This Invoice ADR is therefore a **thin conformance
reference plus the enforcement that is Invoice's local responsibility** — it does not
restate the contract. Where this document and the Suite contract disagree, the Suite
contract wins.

Federation identifiers (env vars, JWT claims, the org-link column) are owned by the
Suite terminology registry (its §4–6). Per the Invoice naming rule (CLAUDE.md;
terminology registry is binding), Invoice reproduces those spellings **verbatim** in
its own registry in this PR — see `docs/explanation/terminology.md` §6. The most
load-bearing alignment: the federation link attribute is the Suite-registered
`organizations.external_id` (claim `org_external_id`); the spelling `suite_org_id` is
**prohibited** in both repos.

## Decision

Adopt the Suite federation participation contract (Suite ADR 0012). Invoice's
conformance is recorded here; the Invoice-local enforcement points are:

### 1. Membership is a reversible toggle; no data moves

- Membership is `NENE_SUITE_MODE` (**unset / `0` = standalone, `1` = suite**) plus
  federation config in `.env`. Join and leave are **configuration changes only**.
- The Invoice database is **never** copied to or shared with the suite. Issued
  documents, document numbering, issuer profile, and payments stay local in **both**
  modes (ADR 0002, ADR 0006). The suite holds an organization roster + identity
  directory only.
- `export`/`import-install` (Invoice ADR 0017) is an **independent** relocation/DR
  capability — **not** the join/leave mechanism.

### 2. Organization UUID — local id is the billing anchor; `external_id` is the link

- Invoice's **canonical** organization identifier stays the **local org id** (column
  `organization_id`, §3) — the immutable anchor for issued documents and numbering.
  It never changes on join or leave.
- The federation identifier is **`organizations.external_id`** (value
  `org_external_id`), a **nullable** link on the org row: minted by the suite for
  suite-first installs, or populated after the fact on a standalone-first join (1:1).
- **1:1 only — merge is impossible by construction** (issued documents are bound to
  the local org id; two local rows with distinct issued histories cannot collapse).
  Billing/compliance reference the local id; federation/SSO reference `external_id`.
- **Hard delete of an org is prohibited** when issued documents exist — **soft-disable
  only**.

### 3. Two trust domains — local session stays HMAC; federation verifies via JWKS

- **Invoice local session** (ADR 0014: in-memory JWT + httpOnly refresh) keeps being
  signed with **`NENE2_LOCAL_JWT_SECRET`**, a **sibling-generated, sibling-local** key
  the suite does **not** distribute. It guards Invoice's domain APIs. **Unchanged.**
- **Suite federation assertion** is **asymmetric**, verified via the published JWKS
  (`NENE_SUITE_JWKS_URL`). Invoice holds **no federation signing key** — verify-only.
- **SSO authenticates login only.** Invoice exchanges a valid suite assertion for its
  **own** ADR 0014 local session and authorizes domain APIs with that session alone. A
  leaked suite assertion cannot reach billing APIs — **fail-closed is preserved**.

### 4. Provisioning, roles, fallback

- **JIT provisioning**: on first successful suite-assertion login, Invoice creates the
  local user keyed by `email` and maps the **coarse suite role claim** to Invoice's own
  **Capability** vocabulary (Invoice owns its capabilities, ADR 0006; local override
  allowed).
- **Local authentication remains** as a break-glass / fallback path; it is **not**
  deleted on join.

### 5. Hub-unavailability

- Existing sessions **continue** (Invoice holds its own session; no per-request hub
  call; refresh is local).
- New login while the hub is down → fall back to **local password (break-glass)**.
- Organization roster is **stale-tolerant / eventually consistent** (last synced
  mirror); only new orgs/members are delayed. No daemon/cron is required to keep
  Invoice functional (ADR 0003).

### 6. Detach (leave) lock-out prevention

Before leaving, Invoice **must confirm a working local admin credential**. On leave:
`NENE_SUITE_MODE` returns to standalone, login reverts to local password, last-synced
org names are retained, federation link ids are **deactivated (not deleted)**, and the
hub↔Invoice service token is revoked. Documents and numbering are untouched.

### 7. Compliance

Invoice is SSOT for its domain (issued documents, numbering, issuer profile, payments)
— **never delegated to or stored in the suite**. This ADR inherits the Suite ADR 0012
§11 guardrails and the 2026-05-31 公認会計士・税理士 sign-off, and introduces **no new
compliance obligation** (no change to immutability, numbering, rounding, retention).

## Consequences

**Benefits**

- One suite-owned normative contract; this ADR is a thin reference, preventing
  two-repo drift.
- Join/leave are reversible config toggles, zero data movement, no destructive ops.
- Asymmetric verify-only posture contains blast radius and preserves fail-closed auth.
- Standalone and suite installs both work; suite membership is optional.

**Costs / risks**

- Adds a second token/trust domain to reason about; the local session and the suite
  assertion must never be conflated (the §3 separation is the guard).
- Adopts suite-side dependencies (enrollment exchange, JWKS, roster API,
  externally-installed self-registration) that must exist before suite mode is usable
  — already tracked on the Suite side as ADR 0012 follow-ups.

**Follow-up** (separate Invoice issues; this ADR is direction only)

- Schema: `organizations.external_id` (nullable) with **parity** — migration +
  installer `schema.sql` + dev SQLite ALTER.
- Auth: suite-assertion verification (JWKS, `kid` rotation) → exchange → mint local
  ADR 0014 session; JIT provisioning + role→Capability mapping; keep local fallback.
- Mode/UI: `NENE_SUITE_MODE` plumbing; join (enrollment token) and leave (break-glass
  guard) flows; discovery health+capabilities endpoint (service-token authed).
- Terminology: federation identifiers registered in this PR (§6); grep for prohibited
  spellings before merge.
- Security review of asymmetric verification / key handling at implementation time
  (per Suite ADR 0012: a follow-up, not a precondition).

## Related

- Normative contract: **NeNe Suite ADR 0012 (accepted 2026-06-19)** — owns the
  enrollment, JWKS, claim, and roster schema.
- Issue: `#486`
- PR: `#489`
- Terminology: `docs/explanation/terminology.md` §6 (Suite federation, cross-repo).
- Related: ADR 0002 (separate from siblings / HTTP-only), ADR 0006 (multi-tenancy /
  roles), ADR 0014 (auth session / cookies), ADR 0015 (location-independent install /
  subdir bundling), ADR 0017 (export/import-install — independent),
  `docs/explanation/accounting-compliance.md` (immutability / numbering — unchanged).
- Supersedes: none
- Superseded by: none
