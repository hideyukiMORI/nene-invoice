# Sibling Product Integration

NeNe Invoice integrates with other NeNe ecosystem products **via HTTP only**. See ADR 0002.

## Dependency direction

```
NeNe Invoice  →  HTTP  →  NeNe Records / NeNe Concierge / NeNe Corpus (optional)   (outbound, Invoice consumes)
NeNe Clear    →  HTTP  →  NeNe Invoice API                                          (inbound, Invoice is upstream SoR)
```

Never embed NeNe Invoice code in sibling repositories. Never share databases.

## Planned integrations

| Sibling | Direction | Use case | Phase |
| --- | --- | --- | --- |
| **NeNe Records** | Invoice → Records (read) | Import product names and prices for line items | Phase 4+ |
| **NeNe Concierge** | Concierge → Invoice (write via webhook/API) | Create draft client or quote from scenario lead capture | Phase 4+ |
| **NeNe Corpus** | None required | No default integration | — |
| **NeNe Clear** | **Clear → Invoice** (read + scoped write) | Bank-deposit reconciliation: read invoices/outstanding, create/void payments. Invoice stays system of record. | See ADR 0009 |

## Downstream consumer: NeNe Clear (入金消込・督促)

NeNe Clear is the first **downstream** consumer that performs **scoped writes**.
NeNe Invoice is the **system of record** for billing figures and the
authoritative outstanding balance; Clear holds the bank evidence and the
reconciliation link only. Contract (binding once both repos accept):
`nene-clear/docs/integrations/invoice-upstream-contract.md`; our acceptance and
architecture: **ADR 0009**.

- **Surface:** a dedicated service namespace **`/api/*`** (separate OpenAPI doc),
  distinct from the operator `/admin/*` surface.
- **Auth:** NeNe Invoice issues a **service token** (machine principal) scoped to
  the operator's `organization_id`(s) and to `read:invoices` + `write:payments`.
- **Writes:** idempotent payment create (with `external_reference`) and
  void-with-audit; over-allocation rejected (`payment-exceeds-outstanding`).
- **Status:** contract accepted (ADR 0009); **read + write API shipped** (§2/§3).
  Read: `GET /api/invoices` (+ filters: status/overdue/client/due/outstanding) and
  `GET /api/invoices/{id}` (with `outstanding_cents` + payment history). Write
  (税理士 sign-off given 2026-05-30): `POST /api/invoices/{id}/payments` (idempotent,
  `external_reference`, over-allocation → `payment-exceeds-outstanding`) and
  `POST …/payments/{paymentId}/void` (void-with-audit, idempotent). All behind
  service-token auth; OpenAPI `docs/openapi/service-api.yaml`. Mint tokens:
  `php tools/issue-service-token.php --org=N --scopes=read:invoices,write:payments`.
- **Token registry + revocation (#416):** issued tokens are recorded in
  `service_tokens` (metadata + `jti` only — the token value is never stored). The
  operator API `/admin/service-tokens` (admin oversight) issues, lists, and revokes
  them, and the CLI issuer registers too. A revoked token's `jti` is rejected at
  request time by `ServiceScopeMiddleware` (`401 service-token-revoked`). Legacy
  tokens issued before the registry carry no `jti` and rely on signature + `exp`.
  Remaining: Clear-side contract tests; operator **UI** for the registry (#417).

## Environment variables (planned)

Document in `.env.example` when clients land:

| Variable | Purpose |
| --- | --- |
| `NENE_RECORDS_API_BASE_URL` | Optional product catalog upstream |
| `NENE_RECORDS_BEARER_TOKEN` | Read-only token for Records API |
| `NENE_CONCIERGE_WEBHOOK_SECRET` | Verify inbound lead webhooks |

For NeNe Clear, the direction is reversed: **NeNe Invoice issues** the service
token; Clear stores `NENE_INVOICE_API_BASE_URL` / `NENE_INVOICE_BEARER_TOKEN` in
*its* env. On our side, service-token signing/verification config is defined with
the `/api/*` auth work (ADR 0009 follow-up), not here.

## Implementation rules

- HTTP clients live in `src/Upstream/`.
- UseCases depend on interfaces, not concrete HTTP clients.
- Upstream failures must degrade gracefully (local manual entry always available).
- Contract tests when upstream OpenAPI is stable.

## Reporting bugs

| Symptom | Open Issue in |
| --- | --- |
| Missing Records catalog API | nene-records |
| Concierge action node needs Invoice endpoint | nene-concierge (or here if Invoice API is the deliverable) |
| Clear cannot read invoices / write payments (`/api/*`) | nene-invoice (this repo — ADR 0009 follow-up) |
| NENE2 middleware / Problem Details | NENE2 |
