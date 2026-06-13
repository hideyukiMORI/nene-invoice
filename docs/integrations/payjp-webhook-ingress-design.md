# PAY.JP Webhook Ingress — Design Note

Design input for **Issue #431** (webhook ingress: request authentication +
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
- Authentication is by **webhook token header** (`X-Payjp-Webhook-Token`), not
  session — a dedicated token-verification middleware stands in where
  `ServiceScopeMiddleware` would be for service tokens.
- Body is parsed with `JsonRequestBodyParser`; errors returned as Problem Details.

## Request authentication (gateway-specific) — VERIFIED 2026-06-13

> **Correction.** An earlier draft of this note assumed Stripe-style HMAC
> signature verification. PAY.JP does **not** sign webhooks. Verified against
> <https://docs.pay.jp/v1/webhook>.

- PAY.JP sends a **shared secret token** in the header
  `X-Payjp-Webhook-Token: whook_…`. The receiver authenticates the webhook by
  **constant-time comparing** this header against the configured token for the
  account. There is no HMAC over the body.
- Reject a missing/incorrect token with `401` (Problem Details slug
  `invalid-webhook-token`). The expected token is stored via gateway config
  (Issue #432), never logged, never echoed.
- The token does not cover the body, so the body is **untrusted**: never treat
  webhook amounts as authoritative for ledger truth beyond matching the resolved
  link/invoice. Re-fetch the charge from the PAY.JP API if stronger assurance is
  needed before recording (decide in the impl PR).
- This verification sits behind `PaymentGatewayInterface`. The contract is
  "authenticate this request", so the Stripe adapter (ADR 0013, second adapter)
  can supply its own HMAC `Stripe-Signature` scheme without reshaping the flow.
  **Do not bake an HMAC assumption into the interface.**

## Idempotency

- Use the PAY.JP **event id** (top-level `id`, e.g. `evnt_…`) as the
  `idempotency_key` passed to `RecordPaymentInput`. A retried/duplicated webhook
  with the same id returns the same `payment` — no double-booking. This reuses the
  existing repository-level idempotency, not a new store.
- **Retry contract (verified):** PAY.JP retries at **3-minute intervals, up to 3
  times**, on a 4xx/5xx response; it requires HTTP **200**. The handler must
  therefore be safe to call up to 4 times and respond `200` once the event is
  recorded (including the duplicate-id case).

## Verified event JSON shape

Top-level event fields (`<https://docs.pay.jp/v1/webhook>`):

```jsonc
{
  "object": "event",
  "id": "evnt_…",        // event id → idempotency_key
  "type": "charge.succeeded",
  "created": 1750000000,  // unix seconds → paid_at source
  "livemode": true,
  "pending_webhooks": 1,
  "data": { /* the charge object, same shape as the API response */ }
}
```

Settlement event type confirmed: **`charge.succeeded`**. The nested charge id
(`data.id`, `ch_…`) is the `external_reference`.

## Tenant & invoice resolution

- The webhook payload references a **charge**, not our `organization_id` /
  invoice directly. The link from a PAY.JP charge back to a `payment_link`
  (and thus org + invoice) must be carried by **something we set when the charge
  is created** — either the charge's `metadata` or a stored
  `payment_links.gateway_session_id`. The webhook then reverse-looks-up the
  owning `payment_link` (`findByGatewaySessionId` / by metadata), recovers tenant
  + invoice, records the payment, and sets the link `status = paid`. **No
  cross-tenant access** (ADR 0006).
- If the charge maps to no known link → `200` + audit log, **do not** create an
  orphan payment (200 so PAY.JP stops retrying a permanently-unresolvable event).

> ⚠️ **Prerequisite gap.** The resolution key above has **no producer yet**.
> #436 persists payment links but leaves `gateway_session_id` null (lazy session
> creation deferred). Building this webhook before the **charge/session creation
> path** (the gateway adapter + public `/pay/{token}` page that creates the
> PAY.JP charge with our metadata and stores `gateway_session_id`) yields a
> handler that cannot be wired or end-to-end tested. **That producer issue must
> land first** — see "Sequencing" below.

## Event → payment mapping (settlement only, for #431)

| PAY.JP event | Action |
| --- | --- |
| `charge.succeeded` | resolve link → `RecordPaymentInput(method: 'card', paid_at: <from `created`>, external_reference: <`data.id`>, idempotency_key: <event `id`>)` → existing use case; then mark link `paid` |
| `charge.failed` | no payment; audit log; link stays unpaid |
| refund / chargeback | **out of scope here** — see boundary below |

`method` value (`card`) is a **new identifier** and MUST be registered in
`docs/explanation/terminology.md` in the implementation PR.

## Sequencing (corrected)

1. **Gateway adapter + charge/session creation** (new issue, prerequisite):
   `PaymentGatewayInterface` + PAY.JP adapter; public `/pay/{token}` creates the
   charge carrying our `payment_link` reference and persists
   `gateway_session_id`. This is the **producer** the webhook resolves against.
2. **#431 webhook ingress** (this note): consumes events, records settlement.

Until step 1 exists, #431 can only be implemented contract-first against fakes
(no real wiring). Recommend doing step 1 first.

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
- Rejections (bad token, unknown charge, expired link) are logged but return the
  minimal status to the gateway.

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
- Problem Details slugs: `invalid-webhook-token` (and any added)
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
