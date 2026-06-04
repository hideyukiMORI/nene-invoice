# ADR 0008: Audit Logging of All Mutating Operations

## Status

accepted

## Context

`accounting-compliance.md` §8 requires an audit trail for billing-sensitive
actions, and the maintainer wants **every** mutating operation to record who
changed what, including the **before and after** state. A reviewer (or an
auditor) must be able to reconstruct the history of any record.

We need a consistent, cross-cutting mechanism that works for all current and
future domains (organizations, users, clients, company settings, and the coming
quotes / invoices / payments) without scattering ad-hoc logging.

Alternatives considered:

1. **Middleware-level logging** — rejected; middleware sees the HTTP request but
   not the domain before/after state, and cannot cleanly name the entity changed.
2. **Repository-level logging** — rejected; repositories know the row but not the
   actor (that is request/auth context) nor the business action name.
3. **UseCase-level recording via an `AuditRecorder`** (chosen) — the use case has
   both the actor/tenant context and the before/after entity state, and names the
   business action. This is where the change is meaningful.

## Decision

A dedicated `audit_logs` table records one row per mutating operation:

| Column | Meaning |
| --- | --- |
| `actor_user_id` | The authenticated user who performed it (null for system) |
| `organization_id` | Tenant the change belongs to |
| `action` | `{entity}.{verb}` (e.g. `client.created`, `user.updated`, `client.deleted`) |
| `entity_type` / `entity_id` | What was changed |
| `before_json` | Sanitized snapshot before the change (null for create) |
| `after_json` | Sanitized snapshot after the change (null for delete) |
| `created_at` | When |

- **Recording happens in the UseCase** via `Audit\AuditRecorder`. Use cases
  already receive the tenant context and fetch the "before" state; handlers pass
  the **actor user id** (from token claims via `AuthContext`).
- **Before/after are sanitized snapshots** produced by the same `*Response`
  presenters used for API output, so **secrets (e.g. `password_hash`) are never
  written to the audit log**. The field-level diff is derivable from the two
  snapshots.
- **All create / update / delete operations** record an entry. Reads are not
  audited. Soft deletes record a `*.deleted` action with the before snapshot.
- **Non-CRUD state-changing events are also recorded** when they are
  business-meaningful and compliance-relevant, using the same `{entity}.{verb}`
  convention with `before = null`:
  - `invoice.sent` — the invoice PDF was emailed to the client; `after` is the
    sanitized invoice snapshot (proof of the content that went out).
  - `invoice.download_token_issued` — a time-limited public download link was
    generated; `after` carries only the non-secret `expires_at`. The raw token
    and its SHA-256 hash are **never** written to the audit trail.
- New domains (quotes, invoices, payments, …) record audit from the start.

## Consequences

**Benefits**

- Uniform, compliance-aligned trail of who changed what, with before/after.
- Secrets excluded by reusing sanitized presenters.
- Future-proof: new mutating use cases follow the same pattern.

**Costs / limitations**

- Use cases gain an `AuditRecorder` dependency and an `actorUserId` argument.
- **Recording is synchronous and best-effort after the mutation, not yet in the
  same DB transaction.** A crash between the mutation and the audit write could
  drop an entry. Wrapping mutation + audit in one transaction is a planned
  follow-up (the framework provides a transaction manager).

**Follow-up**

- Retrofit Organization / User / CompanySettings use cases.
- Add a read endpoint (`GET /admin/audit-logs`) for admins/superadmin.
- Make mutation + audit atomic via a transaction boundary.

## Related

- Compliance: `docs/explanation/accounting-compliance.md` §8
- Issue: `#51`
