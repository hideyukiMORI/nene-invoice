# Requirements

Functional and compliance requirements for NeNe Invoice. MVP scope maps to **Phase 1–3** unless noted.

See also: [`product-vision.md`](./product-vision.md), [`domain-model.md`](./domain-model.md).

---

## 1. User roles

| Role | Capabilities | Phase |
| --- | --- | --- |
| **admin** | Full CRUD on clients, quotes, invoices, payments, company settings | 1 |
| **viewer** (optional) | Read-only access to documents and reports | 3+ |
| **public client** | Download invoice PDF via time-limited token URL — no login | 2 |

Phase 1: single admin user (JWT). Multi-user RBAC is Phase 3+.

---

## 2. Core entities (MVP)

| Entity | Purpose | Key fields |
| --- | --- | --- |
| **company_settings** | Issuer (自社) profile | legal_name, address, phone, email, **registration_number**, bank_name, bank_branch, account_type, account_number, logo_url (optional) |
| **client** | Buyer (取引先) | name, contact_name, email, billing_address, **registration_number** (optional for B2B qualified invoice) |
| **quote** | Estimate (見積書) | client_id, quote_number, issued_at, valid_until, status, subtotal_cents, tax_cents, total_cents, notes |
| **invoice** | Bill (請求書) | client_id, quote_id (optional), invoice_number, issued_at, due_at, status, subtotal_cents, tax_cents, total_cents, **is_qualified_invoice** |
| **line_item** | Row on quote or invoice | parent_type (quote/invoice), parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order |
| **payment** | Payment record | invoice_id, paid_at, amount_cents, method (bank_transfer/cash/other), notes |

All money: **integer cents**. Tax rate: **basis points** (1000 = 10.00%).

---

## 3. Japan qualified invoice (適格請求書) — required fields

When `is_qualified_invoice = true`, the system must enforce and render:

### Issuer (supplier)

- [ ] Legal name (氏名又は名称)
- [ ] Address (住所又は所在地)
- [ ] **Registration number** (登録番号) — format `T` + 13 digits
- [ ] Issue date (請求書の交付年月日)

### Buyer (optional on simplified invoices, required for full B2B)

- [ ] Name (氏名又は名称)
- [ ] Address (住所又は所在地) — when provided

### Transaction details

- [ ] Line items: description (取引内容), tax rate per line or document
- [ ] Taxable amount per rate category (税率ごとの対価の額)
- [ ] Consumption tax per rate category (税率ごとの消費税額)
- [ ] Total billing amount (請求金額)

### Validation rules (API layer)

- Registration number regex: `^T[0-9]{13}$`
- Tax rates allowed: 1000 (10%), 800 (8% reduced) — extensible via ADR
- Invoice cannot be marked qualified if issuer registration_number is empty
- PDF totals must match API-calculated cents (single source: UseCase)

---

## 4. Document lifecycle

| From | Action | To | Phase |
| --- | --- | --- | --- |
| — | Create quote | quote `draft` | 1 |
| draft | Send / finalize | quote `sent` | 1 |
| sent | Accept | quote `accepted` | 1 |
| sent | Reject / expire | quote `rejected` / `expired` | 1 |
| accepted | Convert | invoice `draft` | 1 |
| — | Create invoice directly | invoice `draft` | 1 |
| draft | Issue | invoice `issued` | 1 |
| issued | Record full payment | invoice `paid` | 1 |
| issued | Partial payment | invoice `partially_paid` | 2 |
| issued | Past due_at unpaid | invoice `overdue` (computed) | 1 |

Quote numbers and invoice numbers: auto-increment per organization with configurable prefix (e.g. `EST-2026-001`, `INV-2026-001`).

---

## 5. MVP features by phase

### Phase 1 — API only

- [ ] Company settings CRUD
- [ ] Client CRUD + soft delete
- [ ] Quote CRUD + line items + status transitions
- [ ] Invoice CRUD + convert from quote + line items
- [ ] Payment create + list by invoice
- [ ] Qualified invoice field validation
- [ ] Admin JWT auth
- [ ] OpenAPI 3.1 + PHPUnit + PHPStan 8

### Phase 2 — Admin UI + PDF

- [ ] React admin SPA (clients, quotes, invoices, payments, settings)
- [ ] Server-side qualified invoice PDF (Japanese layout)
- [ ] Quote PDF (optional, simpler layout)
- [ ] Email invoice PDF via SMTP
- [ ] Public PDF download token URL
- [ ] Dashboard: unpaid / overdue summary

### Phase 3 — Tier A shared hosting

- [ ] Web installer (MySQL credentials, admin user, company name)
- [ ] Release ZIP build script
- [ ] Operator guide (Japanese)
- [ ] Same-origin admin on shared hosting

### Phase 4 — Ecosystem + extensions

- [ ] NeNe Records product import for line items
- [ ] NeNe Concierge webhook → draft client / quote
- [ ] MCP tool catalog (read + write with auth)
- [ ] CSV export for accounting software
- [ ] Payment gateway link (Stripe, etc.) — optional

---

## 6. API requirements

- JSON API, OpenAPI 3.1 contract
- RFC 9457 Problem Details for errors
- snake_case JSON properties
- Pagination: `limit`, `offset`, `items` envelope
- Admin routes under `/admin/…`
- `GET /health` unauthenticated

---

## 7. Security requirements

- Admin JWT for mutating routes
- PDF download tokens: random, time-limited, scoped to one invoice
- No stack traces in production responses
- Secrets in `.env` only — never committed
- Audit log for invoice issue and payment record (Phase 2+)

---

## 8. Explicit non-goals

| Item | Reason |
| --- | --- |
| General ledger / journal entries | Different product category; export to accounting SaaS instead |
| Payroll / 給与 | Out of scope |
| Expense receipts / 経費精算 | Out of scope |
| Inventory / stock | NeNe Shop territory |
| PEPPOL / 電子インボイス network | Phase 4+ research; PDF first |
| Multi-currency | JPY only for Phase 1–3 |
| Consumption tax filing (申告) | Operator exports data; no tax return generation |

---

## 9. Acceptance tests (Phase 3 smoke)

1. Install on clean MySQL via web installer.
2. Enter company profile with valid `T` registration number.
3. Create client + quote with 2 line items (10% and 8% tax).
4. Convert to invoice, issue, download qualified invoice PDF.
5. Record payment → invoice status `paid`.
6. List overdue invoices returns empty after payment.

---

## Related

- Domain model: [`domain-model.md`](./domain-model.md)
- Naming: [`../development/naming-conventions.md`](../development/naming-conventions.md)
- Roadmap: [`../roadmap.md`](../roadmap.md)
