# ADR 0012: Card Payment on Issued Invoices (Receiver Side)

## Status

accepted

## Context

NeNe Invoice currently manages quote and invoice issuance plus manual payment
reconciliation (`payment`). Issuers want to shorten their cash-collection cycle
by letting **payers settle an issued invoice by card** through a hosted payment
link or form. This is the **receiver side** of card payment (the issuer gets
paid), which is a different domain from NeNe Payout (the **payer** executing card
payments).

The decision is constrained by three hard requirements specific to this product:

1. **Self-hosted OSS distribution.** Operators are Japanese SMBs running their
   own deployment (Tier A shared hosting / Tier B Docker — ADR 0003). We must
   not push PCI DSS card-data scope onto these operators. Any design where a card
   PAN touches the application, its database, or the operator's server is
   rejected.
2. **Accounting and tax compliance is non-negotiable** (`docs/explanation/accounting-compliance.md`).
   Card settlement introduces processor fees, net-of-fee deposits, refunds, and
   chargebacks. How these map onto `payment` records and the issued invoice
   amount has more compliance risk than the payment flow itself.
3. **Separation from sibling products (ADR 0002).** NeNe Payout may share a
   payment-gateway abstraction, but billing logic must not cross product
   boundaries.

Alternatives considered for card capture:

1. **Self-hosted card form** (app collects PAN) — rejected. Forces SAQ-D /
   full PCI DSS scope onto every self-host operator. Non-starter for OSS.
2. **Direct processor API with our own fields + tokenization JS** — rejected.
   Even with client-side tokenization this commonly lands operators in SAQ-A-EP,
   which is more scope than we will impose by default.
3. **Hosted gateway (redirect or processor-hosted iframe), tokens only**
   (chosen) — card data is entered on the processor's hosted page/iframe; the
   application only ever sees a session id / payment token and webhook events.
   Keeps the self-host operator at **SAQ-A**.

## Decision

Card payment on issued invoices is added as an **optional, pluggable, hosted-only**
capability. The following are binding constraints, not implementation detail:

### 1. SAQ-A constraint (inviolable)

- The card PAN MUST NOT pass through the application, its database, or the
  operator's server. Only **hosted redirect or processor-hosted iframe** flows
  are permitted.
- NeNe Invoice stores only opaque references (gateway session id, payment intent
  id, token) and webhook event payloads — never card numbers, never CVV.
- A self-host operator who enables card payment must remain at **PCI DSS SAQ-A**.
  Any future gateway adapter that would raise that scope requires a new ADR.

### 2. Pluggable gateway via `PaymentGatewayInterface`

- A `PaymentGatewayInterface` abstracts the processor behind the existing
  `Handler → UseCase → RepositoryInterface` layering. Concrete adapters live
  under a `Payment/Gateway/` namespace.
- First-class adapter target is deferred to follow-up (candidates: **Stripe
  Checkout**, **PAY.JP** — both hosted, both SAQ-A compatible, PAY.JP being
  Japan-domestic). This ADR does not yet pick the launch gateway.
- The interface definition MAY be shared in spirit with NeNe Payout, but
  **no billing logic crosses the product boundary** (ADR 0002). Sharing stops at
  the interface contract; invoice/payment domain code stays in this repo.

### 3. Payment link lifecycle

- A decision generates a per-invoice payment link (URL + expiry). Links are
  expirable and revocable; an expired or revoked link cannot be paid.
- Link state is owned by the NeNe Invoice database.

### 4. Settlement → reconciliation (compliance-bound)

- On a gateway **webhook** confirming settlement, a `payment` record is created
  and the invoice status updated automatically.
- Fee handling, **net-of-fee deposit** vs gross amount, refunds, and chargebacks
  MUST be modelled against `docs/explanation/accounting-compliance.md`. This area
  requires **tax-advisor (税理士) sign-off**, per CLAUDE.md non-negotiable
  compliance rule. The sign-off is a **release gate, not an implementation gate**:
  the accounting model may be implemented beforehand, but card payment MUST NOT
  be **released** until 税理士 sign-off is recorded (#430). Implementing ahead of
  sign-off is done **at the risk of rework** if the model is rejected. This ADR
  fixes the constraint, not the ledger design.

### Scope of this ADR

- `accepted` here means: **direction and constraints are agreed; no code yet.**
- Out of scope / deferred to follow-up Issues+ADR: launch gateway selection,
  the fee/refund accounting model (needs 税理士 review), webhook signature and
  idempotency design, and Admin UI for gateway configuration / connectivity test.

## Consequences

**Benefits**

- Eliminates the most manual part of the workflow (hand reconciliation) via
  webhook-driven `payment` updates.
- Self-host operators stay at SAQ-A — no PCI DSS burden imposed by default.
- Gateway is swappable; not locked to one processor.

**Costs**

- Webhook intake adds a public, signature-verified, idempotent ingress path to
  secure and test.
- Fee/refund/chargeback accounting is genuinely hard and gated on 税理士 review.
- Cross-repo coordination if the interface is shared with NeNe Payout.

**Follow-up**

- New Issue + ADR: launch gateway selection (Stripe Checkout vs PAY.JP).
- New Issue: fee/refund/chargeback accounting model + 税理士 sign-off, updating
  `docs/explanation/accounting-compliance.md`.
- New Issue: webhook ingress (signature verification + idempotency).
- New Issue: Admin gateway configuration UI + connectivity check.
- Register any new identifiers (states, fields, Problem Details slugs) in
  `docs/explanation/terminology.md` in the implementing PR.

## Related

- Issue: `#427`
- PR: `#428` (proposed), `#429` selection ratified by ADR 0013
- Launch gateway: `docs/adr/0013-launch-payment-gateway-payjp.md`
- Product separation: `docs/adr/0002-separate-from-sibling-products.md`
- Deployment tiers: `docs/adr/0003-dual-deployment-tiers.md`
- Tax rounding: `docs/adr/0004-tax-rounding-per-rate.md`
- Accounting compliance (binding): `docs/explanation/accounting-compliance.md`
- Supersedes: none
- Superseded by: none
