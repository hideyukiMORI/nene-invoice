# PAY.JP Webhook Ingress — Design Note

Design input for **Issue #431** (webhook ingress: signature verification +
idempotency) under **ADR 0012** (card payment, hosted-only / SAQ-A) and
**ADR 0013** (launch gateway = PAY.JP). This note fixes the design so the
implementation PR can follow established Payment-domain conventions. **No code in
this PR.**

## Key reuse finding

`RecordPaymentUseCaseInterface::execute(?int $orgId, int $invoiceId, RecordPaymentInput)`
already exists and is **idempotent on `idempotency_key`**, and `RecordPaymentInput`
already carries `method`, `paid_at`, `external_reference`, and `idempotency_key`
(`src/Payment/RecordPaymentInput.php`, `src/Payment/Payment.php`). The
NeNe Clear external write (`ServiceApi/RecordServicePaymentHandler`) is a working
precedent for an **external, idempotent** payment write.

→ The webhook does **not** introduce a new payment-writing path. It maps a
verified PAY.JP settlement event onto `RecordPaymentInput` and calls the existing
use case. This keeps invoice status transitions and over-allocation handling
(`422 payment-exceeds-outstanding`) centralized and unchanged.

## Endpoint & routing

- Public route, **no admin JWT**: `POST /webhooks/payjp`. Registered in a
  `Payment/Gateway/` route registrar, mirroring the public-route pattern in
  `InvoiceDownloadTokenRouteRegistrar` (`/invoices/download/{token}`).
- Authentication is by **webhook signature**, not session — a dedicated
  signature-verification middleware stands in where `ServiceScopeMiddleware`
  would be for service tokens.
- Body is parsed with `JsonRequestBodyParser`; errors returned as Problem Details.

## Signature verification (gateway-specific)

- Verify the PAY.JP webhook signature against a per-organization (or per-gateway)
  signing secret **before** parsing intent. Reject unverified with `401`
  (Problem Details slug `invalid-webhook-signature`).
- Secret is stored via the gateway config (Issue #432), never logged, never
  echoed. Constant-time comparison.
- This is the first hard requirement that differs from Stripe; the verification
  step sits behind `PaymentGatewayInterface` so the Stripe adapter (ADR 0013,
  second adapter) supplies its own `Stripe-Signature` scheme without reshaping
  the flow.

## Idempotency

- Use the PAY.JP **event id** (or charge id) as the `idempotency_key` passed to
  `RecordPaymentInput`. A retried/duplicated webhook with the same id returns the
  same `payment` — no double-booking. This reuses the existing repository-level
  idempotency, not a new store.
- PAY.JP retries on non-2xx; the handler must therefore be safe to call N times
  and still respond `200` once the event is recorded.

## Tenant & invoice resolution

- The webhook payload references a charge, not our `organization_id` / invoice
  directly. Resolution path: the **payment link** (ADR 0012 §3, owned by the
  Invoice DB) records the originating `organization_id` + `invoice_id` + gateway
  session/charge id at link-creation time. The webhook looks up that mapping to
  recover tenant + invoice. **No cross-tenant access** (ADR 0006).
- If the charge id maps to no known link → `404`/ignore with audit log; do not
  create orphan payments.

## Event → payment mapping (settlement only, for #431)

| PAY.JP event | Action |
| --- | --- |
| charge succeeded / captured | `RecordPaymentInput(method: 'card', paid_at: <event time>, external_reference: <charge id>, idempotency_key: <event id>)` → existing use case |
| charge failed | no payment; audit log; link stays unpaid |
| refund / chargeback | **out of scope here** — see boundary below |

`method` value (`card` vs a more specific token) is a **new identifier** and MUST
be registered in `docs/explanation/terminology.md` in the implementation PR.

## Accounting boundary (release gate, #430)

Refunds, chargebacks, processor-fee handling, and net-of-fee vs gross deposit are
**deliberately excluded** from #431. They depend on the accounting model under
**#430**, whose tax-advisor (税理士) sign-off is a **release gate** (ADR 0012 /
ADR 0013). #431 implements only the **successful-settlement → payment** path so
it can be built and tested ahead of #430, accepting rework risk if the model
changes.

## Audit & observability

- Every accepted/rejected webhook writes an audit entry (ADR 0008): event id,
  outcome, invoice/org (when resolved). No card data, ever (SAQ-A).
- Rejections (bad signature, unknown charge, expired link) are logged but return
  the minimal status to the gateway.

## Test plan

- Signature: valid passes; tampered body / wrong secret → `401`.
- Idempotency: same event id twice → one `payment`, both responses `200`.
- Mapping: succeeded event → `payment` with `method=card`, `external_reference`
  = charge id, correct `organization_id`/`invoice_id`.
- Over-allocation: settlement exceeding outstanding → existing
  `422 payment-exceeds-outstanding` path (assert no partial state).
- Unknown charge id / expired link → no payment created.
- Tenant isolation: webhook for org A never touches org B data.

## New identifiers to register (impl PR)

- Route: `/webhooks/payjp`
- Problem Details slugs: `invalid-webhook-signature` (and any added)
- `payment.method` value for card settlement
- Any gateway/link mapping field names

All must be added to `docs/explanation/terminology.md` **in the same PR**, per
CLAUDE.md naming rule.

## Open questions for the implementation PR

1. Signing secret scope: per-organization vs per-deployment (ties to #432 config
   model).
2. Payment-link persistence shape (new table vs columns on invoice) — likely its
   own small issue ahead of #431, since webhook resolution depends on it.
3. `paid_at` source: PAY.JP event timestamp vs capture time — pick the one that
   matches the accounting model (#430).

## Related

- ADR: `docs/adr/0012-card-payment-on-invoices.md`,
  `docs/adr/0013-launch-payment-gateway-payjp.md`
- Precedents: `src/ServiceApi/RecordServicePaymentHandler.php`,
  `src/InvoiceDownloadToken/InvoiceDownloadTokenRouteRegistrar.php`
- Reused use case: `src/Payment/RecordPaymentUseCase.php`
- Gateway comparison: `docs/integrations/payment-gateway-comparison.md`
- Issue: `#431` (open for implementation); accounting gate: `#430`
