# ADR 0009: Accept the NeNe Clear Upstream Integration Contract

## Status

accepted

## Context

NeNe Clear (`nene-clear`, private) owns **payment reconciliation and dunning**
(入金消込・督促): it imports bank deposit lines, matches them to invoices, tracks
client credit from over-payment, and sends dunning notices. Its ADR 0009
("Separate Domain from nene-invoice") establishes that Clear and NeNe Invoice are
**separate deployable units** integrated over **HTTP only** — never a shared
database — and that **NeNe Invoice is the system of record for billing figures**
(quotes, invoices, payments, outstanding balances, qualified-invoice copies).

Clear has published a binding request to us:
`nene-clear/docs/integrations/invoice-upstream-contract.md`. It is a hand-off
specifying what NeNe Invoice must expose so Clear can reconcile deposits while we
keep ownership of the numbers. This ADR records our acceptance of that contract
and the architectural decisions it forces; it does **not** implement the
endpoints (sequenced follow-up Issues do).

This aligns with our existing **ADR 0002** (separate from sibling products; HTTP
only) — Clear is simply the first *downstream* consumer that also performs
**scoped writes** (creating/voiding payments), where prior siblings were read-only
or inbound webhooks.

## Decision

We **accept** the contract and commit to the following, to be delivered in
sequenced follow-up PRs (Issue #97 and successors):

### 1. Boundary (restated, binding)

NeNe Invoice remains the **system of record** for invoice figures, the payment
record, and the authoritative **outstanding balance**
(`outstanding_cents = total_cents − Σ valid payments`). Clear holds the bank
evidence (証憑) and the reconciliation link; it never recomputes our figures.
Over-payment is **not** posted to an invoice (no negative outstanding) — the
excess is Clear's `client_credit`. (Clear ADR 0009; contract §1, §3.1.5.)

### 2. A dedicated service API surface — `/api/*`

Clear is a **machine consumer**, not an operator. We expose its operations under a
**separate `/api/*` namespace**, distinct from the operator `/admin/*` surface,
published as its **own OpenAPI document** (so `operationId`s like `listInvoices`
may coexist across the two surfaces without collision). Domain logic (UseCases,
repositories, tax, numbering) is shared; only Handlers/routes/auth differ.

### 3. Service-token authentication (machine principal)

NeNe Invoice **issues a bearer service token** for Clear, representing a
**service principal** scoped to one or more `organization_id`s and to the scopes
**`read:invoices`** and **`write:payments`**. It is independent of human
`Role`/`Capability` (ADR 0006); cross-tenant access is rejected. Clear stores it
only in its own `.env` (`NENE_INVOICE_API_BASE_URL`, `NENE_INVOICE_BEARER_TOKEN`).

### 4. Invariants we guarantee (contract §4)

- Issued invoice figures immutable (already ADR-bound; `accounting-compliance.md`).
- `outstanding_cents` computed and owned by Invoice; exposed on read models.
- `paid_at` is the **bank value date** supplied by Clear — stored as given, never
  overwritten with a posting timestamp.
- **No hard delete** of payments — **void-with-audit** only (already our policy;
  `accounting-compliance.md` §"No hard delete of billing records").
- **Idempotency** on every write via `idempotency_key`; `external_reference`
  (Clear's reconciliation id) is stored and round-trips.
- Integer minimum currency units (`*_cents`), JPY; no float/DECIMAL for money.
- **Audit** on payment create/void with actor = the Clear service principal.
- Over-allocation rejected with `422 payment-exceeds-outstanding`, returning the
  current `outstanding_cents`.

### 5. Stability

`operationId`s and JSON field names on `/api/*` are stable once shipped —
deprecate, never rename (mirrors Clear). New identifiers are registered in
`terminology.md` in the same PR that introduces them.

## Consequences

**Benefits.** A clean books-↔-evidence split an auditor can rely on; the boundary
is enforced by separate surfaces and a least-privilege machine principal; reuse of
all existing billing domain logic.

**Costs / new surface.** A second OpenAPI document + contract tests; a
service-principal auth path beyond the human RBAC model; idempotency storage;
`outstanding_cents` computation exposed on read models; a payment **void** endpoint
(the data model already soft-deletes, but no use case/route exists yet).

**Compliance (binding — `accounting-compliance.md`).** The mechanics align with
existing policy (void-not-delete, auditable payments, integer cents). The points
that touch tax/record-keeping law — treating `paid_at` as the value date, accepting
externally-sourced payments, and keeping over-payment as Clear-side `client_credit`
rather than invoice revenue — must be **confirmed with a licensed 税理士 /
公認会計士** before the write API ships, per the contract's own caveat and our
non-negotiable compliance rule. This ADR records the *engineering* acceptance; the
tax sign-off is a gate on the §3 write-API PR, not on this documentation PR.

**Follow-up (sequenced Issues).**

1. Read API: `GET /api/invoices` (filters + `outstanding_cents` + `currency`),
   `GET /api/invoices/{id}` (outstanding + line items + payment history),
   optional `GET /api/clients`.
2. Write API: idempotent `POST /api/invoices/{id}/payments` (+ `external_reference`,
   over-allocation → `payment-exceeds-outstanding`); `POST …/payments/{id}/void`
   (void-with-audit, idempotent).
3. Service-token auth (org-scoped principal) + Problem Details slugs.
4. `/api/*` OpenAPI document + contract tests; add Clear to `sibling-products.md`.

## Related

- Issue: `#97`
- PR: `#98`
- Upstream contract (source): `nene-clear/docs/integrations/invoice-upstream-contract.md`
- Builds on: ADR 0002 (separate from siblings), ADR 0006 (multi-tenancy/roles),
  ADR 0008 (audit logging)
- Binding compliance: `docs/explanation/accounting-compliance.md`
- See: `docs/integrations/sibling-products.md`, `docs/explanation/terminology.md`
- Supersedes: none
- Superseded by: none
