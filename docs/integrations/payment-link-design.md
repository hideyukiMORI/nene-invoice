# Payment Link — Design Note

Design input for **Issue #436** (per-invoice payment link: URL + expiry +
revocation) under **ADR 0012 §3** and **ADR 0013** (PAY.JP). #436 is a
**prerequisite for #431** — the webhook resolves tenant/invoice by reverse-lookup
of the link record. **No code in this PR.**

## Precedent to mirror

`InvoiceDownloadToken` is the closest existing pattern: a hashed, time-limited,
public token for one invoice (`src/InvoiceDownloadToken/`,
`database/migrations/20260530200000_create_invoice_download_tokens_table.php`).
The payment link reuses its shape and discipline:

- Raw token lives only in memory + URL; DB stores **only the SHA-256 hash**
  (`SecureTokenHelper::generateWithHash()`).
- Org-scoped via `RequestScopedHolder` + org-scoped repository; a foreign invoice
  is invisible at lookup (ADR 0006).
- Generate path is transactional with an audit record (ADR 0008).

Payment links differ in three ways: they are **revocable**, they carry a
**gateway session/charge reference** for webhook reverse-lookup, and they have a
**paid** terminal state.

## Proposed table: `payment_links`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | integer PK | |
| `organization_id` | integer, not null | tenant scope (ADR 0006) |
| `invoice_id` | integer, not null | target invoice |
| `token_hash` | string(64), not null, **unique** | SHA-256 of raw URL token |
| `gateway` | string, not null | `payjp` (registry value) |
| `gateway_session_id` | string, **nullable** | set when the hosted session is created (see open Q1) |
| `status` | string, not null | `active` / `paid` / `expired` / `revoked` (see state machine) |
| `expires_at` | datetime, not null | TTL from creation |
| `paid_at` | datetime, nullable | set when settlement webhook records the payment |
| `revoked_at` | datetime, nullable | set on manual revoke |
| `created_at` | datetime, not null | |
| `updated_at` | datetime, not null | |

Indexes: unique `token_hash`; index `invoice_id`; index `gateway_session_id`
(webhook reverse-lookup); index `organization_id`.

> `status` is derived-but-stored: `expired` is the truth of `expires_at <= now`,
> but a stored status lets the webhook reject a stale link in one read and keeps
> Admin listing cheap. `isExpired(now)` on the model remains the source of truth
> for the time check, mirroring `InvoiceDownloadToken::isExpired`.

## Domain model (sketch)

```php
final readonly class PaymentLink
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public string $tokenHash,
        public string $gateway,          // 'payjp'
        public string $status,           // active|paid|expired|revoked
        public string $expiresAt,
        public ?string $gatewaySessionId = null,
        public ?string $paidAt = null,
        public ?string $revokedAt = null,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    public function isExpired(string $now): bool { return $this->expiresAt <= $now; }
    public function isPayable(string $now): bool
    {
        return $this->status === 'active' && !$this->isExpired($now);
    }
}
```

## State machine

```
            generate
               │
               ▼
            active ──── expires_at <= now ───▶ expired   (terminal)
             │  │
   revoke ───┘  └─── settlement webhook ────▶ paid       (terminal)
      │
      ▼
   revoked  (terminal)
```

- Only `active` (and not expired) links are payable. `expired` / `revoked` /
  `paid` reject payment.
- `paid` is set by the settlement webhook (#431) when it records the `payment`;
  this closes the loop with the webhook design note.

## Use cases

| Use case | Caller | Notes |
| --- | --- | --- |
| `GeneratePaymentLink` | Admin (per invoice) | org-scoped; transactional + audit; returns raw token for the URL |
| `RevokePaymentLink` | Admin | `active → revoked`; idempotent on already-terminal |
| `ResolvePaymentLinkByToken` | public link page / webhook support | hash the raw token, look up, check `isPayable` |
| (status→`paid`) | settlement webhook (#431) | not a new public path; set within the existing payment-record transaction |

## Routing

- Admin: `POST /admin/invoices/{id}/payment-links` (generate),
  `POST /admin/payment-links/{id}/revoke` — mirrors
  `InvoiceDownloadTokenRouteRegistrar` admin route.
- Public: `GET /pay/{token}` — resolves the link and redirects to the PAY.JP
  hosted page. **SAQ-A constraint:** this page carries **no operator-injected
  scripts** (analytics/GTM); card entry happens on PAY.JP's hosted page only.

## Multi-tenancy & security

- Every query is `organization_id`-scoped (ADR 0006). Reverse-lookup by
  `gateway_session_id` (webhook) still filters by the link's own org — no
  cross-tenant read.
- Only the SHA-256 hash is stored; raw token is unguessable (256-bit).
- No card data is ever stored or proxied (SAQ-A, ADR 0012).

## New identifiers to register (impl PR, `docs/explanation/terminology.md`)

- Entity / table: `payment_link` / `payment_links`
- Fields: `gateway`, `gateway_session_id`, `status`, `expires_at`, `paid_at`,
  `revoked_at`
- `status` values: `active`, `paid`, `expired`, `revoked`
- `gateway` value: `payjp`
- Routes: `/admin/invoices/{id}/payment-links`,
  `/admin/payment-links/{id}/revoke`, `/pay/{token}`

## Open questions for the implementation PR

1. **When is the gateway session created?** Eagerly at link generation (store
   `gateway_session_id` immediately) vs lazily on first visit to `/pay/{token}`.
   Lazy avoids creating sessions for links that are never opened; eager makes the
   webhook reverse-lookup simpler. Lean **lazy**, with `gateway_session_id`
   nullable until first visit. Confirm against PAY.JP session lifetime/expiry.
2. **TTL default.** `InvoiceDownloadToken` uses 7 days; a payment link may want a
   longer/configurable window (tie to billing due date?). Decide in impl.
3. **One active link per invoice?** Allow multiple (re-issue) vs enforce a single
   active link. Lean: allow re-issue, auto-revoke prior `active` link on generate.

## Related

- ADR: `docs/adr/0012-card-payment-on-invoices.md` §3,
  `docs/adr/0013-launch-payment-gateway-payjp.md`
- Webhook consumer: `docs/integrations/payjp-webhook-ingress-design.md`
- Precedent: `src/InvoiceDownloadToken/`,
  `database/migrations/20260530200000_create_invoice_download_tokens_table.php`
- Issue: `#436` (open for implementation); blocks `#431`
