# ADR 0013: PAY.JP as Launch Card Payment Gateway

## Status

accepted

## Context

ADR 0012 established that card payment on issued invoices is added as an
optional, pluggable, **hosted-only (SAQ-A)** capability behind a
`PaymentGatewayInterface`, and explicitly deferred the choice of the first
concrete gateway. Issue #429 evaluated candidates; the comparison is recorded in
`docs/integrations/payment-gateway-comparison.md` (checked 2026-06-13).

Both finalists — **Stripe Checkout** and **PAY.JP** — are hosted, SAQ-A
eligible, and support JPY. Distinguishing factors for our target operator
(Japanese SMB self-host):

- **PAY.JP**: processing fee from ~2.59%, JPY-native settlement (no FX), payout
  fee ~¥250, Japan-domestic, JP-language support.
- **Stripe Checkout**: processing fee ~3.6%, possible USD/FX settlement layer,
  but more mature signed-webhook / idempotency / test-mode developer experience.

## Decision

- The **launch (first-class) gateway is PAY.JP**, implemented as the first
  concrete adapter under `Payment/Gateway/` behind `PaymentGatewayInterface`.
- **Stripe Checkout is the designated second adapter.** `PaymentGatewayInterface`
  MUST be designed so Stripe drops in without reshaping the interface — in
  particular it must absorb differing webhook-signature schemes and settlement/FX
  handling.
- All constraints of ADR 0012 remain binding and unchanged. Notably the
  **SAQ-A discipline**: the payment-link page MUST NOT carry operator-controlled
  scripts (analytics/GTM), which would expand scope to SAQ-A-EP. This becomes an
  operator-guide constraint.
- This decision does **not** lift the **release gate** from ADR 0012: the
  accounting model may be implemented first, but card payment MUST NOT be
  released until the accounting model + tax-advisor (税理士) sign-off (#430) is
  recorded. Implementing ahead of sign-off is accepted **at the risk of rework**.

## Consequences

**Benefits**

- Lower processing cost and JPY-native settlement match the JP SMB audience.
- Designing the interface with Stripe as a planned second adapter keeps the
  abstraction honest and avoids PAY.JP-shaped lock-in.

**Costs**

- PAY.JP's ecosystem and tooling are smaller than Stripe's; webhook signature
  verification and idempotency (#431) must be implemented carefully without
  leaning on Stripe-grade libraries.

**Follow-up**

- #431 webhook ingress targets PAY.JP signature/idempotency first.
- #432 Admin gateway config UI ships PAY.JP credentials first.
- Add the "no operator scripts on payment page" rule to the operator guide.
- Register any new identifiers in `docs/explanation/terminology.md` in the
  implementing PR.

## Related

- Issue: `#429`
- PR: `#000`
- Parent decision: `docs/adr/0012-card-payment-on-invoices.md`
- Comparison input: `docs/integrations/payment-gateway-comparison.md`
- Accounting gate (blocks implementation): `#430`
- Supersedes: none
- Superseded by: none
