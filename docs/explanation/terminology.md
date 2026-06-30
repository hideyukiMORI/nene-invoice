# Terminology Registry — Single Source of Truth

**Status: binding.** This file is the **single source of truth** for the
canonical spelling and form of every NeNe Invoice term and identifier:
entities, status values, JSON/DB field names, enums, Problem Details slugs, and
`operationId` stems.

If an identifier appears anywhere in the codebase, OpenAPI, database, tests, or
docs, its spelling **MUST** match this registry **exactly** — same characters,
same case, same separators. There is no "close enough."

## Authority and absolute rules

1. **Exact match is mandatory.** Any spelling variant or typo of a registered
   term is a defect and **blocks merge** — there are no acceptable synonyms or
   abbreviations outside this registry.
2. **Register before you use.** Introducing a new term/identifier, or renaming
   an existing one, **MUST** update this registry in the **same PR**. Code that
   uses an unregistered term does not merge.
3. **No renaming after release.** Shipped `operationId` values and public JSON
   field names are stable; deprecate, do not rename (see `naming-conventions.md`).
4. **Roles of the three docs — do not duplicate, cross-reference:**
   - **`terminology.md`** (this file) — the authoritative **spelling/form** of every identifier.
   - **`glossary.md`** — the **meaning** of product concepts. Its spellings MUST conform to this registry.
   - **`naming-conventions.md`** — the **patterns/rules** that generate names. This registry is the concrete instantiation of those rules.

See: [`glossary.md`](./glossary.md), [`../development/naming-conventions.md`](../development/naming-conventions.md).

---

## 1. Domain entities

| Concept | PHP class / domain folder | Table | Primary FK in JSON/DB |
| --- | --- | --- | --- |
| Tenant | `Organization` | `organizations` | `organization_id` |
| Operator account | `User` | `users` | `user_id` |
| Issuer profile | `Company` (folder); `CompanySettings` (entity) | `company_settings` | — (one per `organization_id`) |
| Company seal | `Company\Seal` (folder) | `company_seal_images` | — (one per `organization_id`) |
| Buyer | `Client` | `clients` | `client_id` |
| Item master | `Item` | `items` | `item_id` |
| Estimate | `Quote` | `quotes` | `quote_id` |
| Bill | `Invoice` | `invoices` | `invoice_id` |
| Document row | `LineItem` | `line_items` | `line_item_id` |
| Template | `Template` | `templates` | `template_id` |
| Receipt | `Payment` | `payments` | `payment_id` |
| Payment link | `PaymentLink` | `payment_links` | `payment_link_id` |
| Number generator | `DocumentSequence` | `document_sequences` | — |
| Integration credential | `ServiceToken` | `service_tokens` | `service_token_id` |
| Recurring billing | `RecurringInvoice` | `recurring_invoices` | `recurring_invoice_id` |
| Bank line (入金明細) | `BankTransaction` | `bank_transactions` | `bank_transaction_id` |
| Payer alias (名義辞書) | `PayerAlias` | `payer_aliases` | `payer_alias_id` |

Domain folders are **PascalCase singular**; tables are **snake_case plural**.

---

## 2. Status values (exact strings)

Stored and transmitted **exactly** as written (lowercase snake_case).

| Owner | Allowed values |
| --- | --- |
| `quote.status` | `draft`, `sent`, `accepted`, `rejected`, `expired` |
| `invoice.status` | `draft`, `issued`, `partially_paid`, `paid` |
| invoice computed | `overdue` (computed flag, not a stored status in Phase 1) |
| `payment.method` | `bank_transfer`, `cash`, `card`, `other` |
| `line_item.parent_type` | `quote`, `invoice`, `template`, `recurring_invoice` |
| `line_item_suggestion.source` | `master`, `history` |
| `user.role` | `superadmin`, `admin`, `member`, `viewer` (ADR 0006) |
| `user.status` | `active`, `invited` |
| service_token status (computed) | `active`, `revoked` |
| `payment_link.status` | `active`, `paid`, `revoked` (expiry derived from `expires_at`, not stored) |
| `payment_link.gateway` | `payjp` (launch gateway — ADR 0013) |
| `recurring_invoice.frequency` | `monthly`, `quarterly` (#503) |
| `bank_transaction.status` | `unmatched`, `matched`, `posted`, `ignored` (#505) |
| `bank_transaction.direction` | `credit`, `debit` (#505) |
| `company_settings.pdf_template` | `standard` (default), `modern`, `classic` (見積/請求 PDF レイアウト — Issue #449) |
| `company_settings.pdf_spacing` | `small`, `medium` (default), `large` (PDF 余白スケール大中小 — Issue #449) |
| `company_settings.pdf_heading_font` | `gothic` (default), `mincho` (PDF 見出しフォント — Issue #449) |
| Capability (enum) | `manage_organizations`, `manage_users`, `manage_company_settings`, `manage_billing`, `view_billing` |
| Org resolution mode | `single` (default), `path`, `subdomain`, `custom_domain` |

Do not invent `cancelled`, `void`, `unpaid`, `pending`, etc. without registering them here first.

---

## 3. Canonical field / column names (snake_case)

| Term | Canonical | Never |
| --- | --- | --- |
| Tenant foreign key | `organization_id` | `org_id`, `tenant_id`, `organizationId` |
| Organization slug | `slug` | `org_slug`, `code` |
| User role | `role` (values in §2) | `user_role`, `permission` |
| User credential | `password_hash` | `password`, `pass_hash` |
| Invoice registration number | `registration_number` | `tax_registration_number`, `invoice_registration_number`, `t_number` |
| Qualified-invoice flag | `is_qualified_invoice` | `qualified`, `is_qualified` |
| Soft-delete flag / time | `is_deleted`, `deleted_at` | `deleted`, `is_del` |
| Subtotal (pre-tax) | `subtotal_cents` | `sub_total_cents`, `subtotal` |
| Tax amount | `tax_cents` | `tax`, `tax_amount` |
| Total | `total_cents` | `amount_cents` (on documents), `grand_total` |
| Unit price | `unit_price_cents` | `price_cents`, `unitprice_cents` |
| Payment amount | `amount_cents` | `paid_cents`, `payment_cents` |
| Outstanding balance | `outstanding_cents` | `balance`, `remaining`, `unpaid_cents` |
| External (Clear) reconciliation id | `external_reference` | `ext_ref`, `clear_id`, `reconciliation_id` |
| Idempotency key | `idempotency_key` | `idempotencyKey`, `dedupe_key` |
| Currency | `currency` (ISO 4217, `JPY`) | `ccy`, `currency_code` |
| Tax rate | `tax_rate_bps` | `tax_rate`, `rate`, `tax_rate_percent` |
| Foreign keys | `client_id`, `quote_id`, `invoice_id` | `clientId`, `client` |
| Polymorphic parent | `parent_type`, `parent_id` | `parentType`, `owner_id` |
| Numbers | `quote_number`, `invoice_number` | `number`, `quote_no` |
| Timestamps | `issued_at`, `due_at`, `paid_at`, `valid_until`, `deleted_at` | `issue_date`, `due_date`, `paidAt` |
| Issuer fields | `legal_name`, `bank_name`, `bank_branch`, `account_type`, `account_number`, `logo_url` | `company_name`, `branch`, `acct_no` |
| PDF appearance (issuer) | `pdf_template`, `pdf_spacing`, `pdf_heading_font` (values in §2) | `template`, `layout`, `spacing_size`, `margin_size`, `font`, `heading_font_family` |
| Company seal (社印) | `image_base64`, `has_seal` | `seal`, `seal_url`, `stamp`, `seal_png`, `image`, `image_data` |
| Billing defaults (issuer) | `default_quote_validity_days`, `default_payment_closing_day`, `default_payment_month_offset`, `default_payment_pay_day` | `quote_validity`, `closing_day`, `payment_site`, `pay_day`, `net_days` |
| Client fields | `name_kana`, `contact_name`, `billing_address` | `kana`, `furigana`, `name_reading`, `contact`, `address` |
| Item master defaults | `default_unit_price_cents`, `default_tax_rate_bps` | `default_price_cents`, `item_price_cents`, `default_rate`, `unit_price` |
| Recurring-billing fields | `frequency` (values §2), `next_run_on`, `last_run_on` (calendar dates like `valid_until`), `is_active` | `interval`, `cycle`, `next_run`, `last_run`, `active`, `enabled` |
| Bank-transaction fields | `value_date`, `direction` (values §2), `amount_cents`, `payer_name`, `bank_reference`, `status` (values §2), `matched_invoice_id`, `matched_payment_id`, `imported_at` | `date`, `txn_type`, `cr_dr`, `amount`, `payer`, `remitter`, `ref`, `bank_ref`, `ext_ref`, `matched_id` |
| Payer-alias fields | `normalized_name`, `client_id` | `payer`, `alias`, `name_key`, `normalized`, `customer_id` |
| Service-token fields | `jti`, `subject`, `label`, `scopes`, `created_by`, `expires_at`, `revoked_at`, `ttl_seconds` | `jwt_id`, `name`, `scope`, `created_user_id`, `expiry`, `revoked`, `ttl` |
| Payment-link fields | `token_hash`, `gateway`, `gateway_session_id`, `status`, `expires_at`, `paid_at`, `revoked_at` | `token`, `session`, `provider`, `expiry`, `paid`, `revoked` |
| Gateway-settings fields | `gateway`, `public_key_masked`, `secret_set`, `webhook_token_set`, `configured`, `ok`, `detail` (`connected`/`not_configured`/`invalid_credentials`/`unreachable`) | `secret_key`, `api_key`, `public_key`, `status` |
| List envelope | `items`, `limit`, `offset` | `data`, `results`, `count` |
| Dashboard read model | `unpaid_count`, `overdue_count`, `outstanding_total_cents`, `recent_unpaid`, `received_this_month_cents`, `received_last_month_cents`, `billed_this_month_cents`, `billed_last_month_cents`, `monthly_billed` (→ `month`, `billed_cents`, `count`), `billed_prev_year_month_cents`, `billed_daily_current` / `billed_daily_prev_month` (→ `day`, `cumulative_cents`) | `monthly_received_cents`, `received_this_month`, `mtd_cents`, `issued_this_month_cents`, `invoiced_cents`, `yoy_cents`, `daily_billed` |
| Receivable aging buckets | `aging` → `current`, `overdue_1_30`, `overdue_31_plus` | `aging_buckets`, `bucket_*`, `over_30` |
| Line-item suggestion read model | `items` → `description`, `unit_price_cents`, `tax_rate_bps`, `usage_count`, `source` (`master`/`history`) | `count`, `times_used`, `frequency`, `default_price_cents`, `origin`, `kind` |

Rules: money columns end in `_cents` (integer); timestamps end in `_at` (except
the documented `valid_until`); booleans use `is_` / `has_`; foreign keys are
`{singular_entity}_id`. See `naming-conventions.md` for the full pattern set.

---

## 4. Problem Details type slugs (kebab-case)

Base URL: `https://nene-invoice.dev/problems/`. Slug is **kebab-case**.

| Slug | Use |
| --- | --- |
| `invalid-json` | Malformed / empty / non-object request body (400; framework `JsonRequestBodyParser`) |
| `validation-failed` | Request body/field validation error (422; `errors[]` carries field + code) |
| `invoice-not-found` | Invoice id/token not found |
| `quote-not-found` | Quote id not found |
| `client-not-found` | Client id not found |
| `item-not-found` | Item-master id not found |
| `template-not-found` | Template id not found |
| `invalid-registration-number` | Registration number not `T` + 13 digits (422) |
| `organization-not-found` | Organization id/slug not found |
| `organization-slug-conflict` | Organization slug already in use (409) |
| `user-not-found` | User id not found |
| `user-email-conflict` | User email already in use (409) |
| `role-not-assignable` | Role cannot be assigned via user management, e.g. superadmin (422) |
| `cannot-delete-self` | A user cannot delete their own account (409) |
| `invalid-credentials` | Login failed — wrong email or password |
| `too-many-requests` | Too many failed login attempts from the source IP (429; login throttling) |
| `invalid-refresh-token` | Refresh cookie missing/expired/invalid or principal ineligible — silent re-auth fails closed (401; ADR 0014) |
| `csrf-token-invalid` | Double-submit CSRF check failed on a cookie-authenticated endpoint (403; ADR 0014) |
| `unauthorized` | Missing or invalid bearer token (framework `BearerTokenMiddleware`) |
| `insufficient-capability` | Authenticated but lacks required capability |
| `organization-not-resolved` | Tenant could not be resolved for the request (404; OrgResolverMiddleware) |
| `organization-inactive` | Resolved organization is inactive (403; OrgResolverMiddleware) |
| `organization-mismatch` | Authenticated user's org does not match the URL-resolved org (403; OrgGuardMiddleware) |
| `invalid-state-transition` | Disallowed status change |
| `company-settings-not-found` | Issuer profile not configured for the organization (404) |
| `qualified-invoice-incomplete` | Missing required qualified-invoice field |
| `payment-exceeds-outstanding` | Payment would exceed the invoice outstanding balance (422; service API) |
| `payment-not-found` | Payment id / external_reference not found (404; service API) |
| `insufficient-scope` | Service token lacks the required scope (403; service API) |
| `service-token-revoked` | Presented service token has been revoked (401; service API) |
| `service-token-not-found` | Service-token id not found in the caller's org (404; operator API) |
| `payment-link-not-found` | Payment-link id not found in the caller's org (404; operator API) |
| `invalid-webhook-token` | PAY.JP webhook `X-Payjp-Webhook-Token` missing or incorrect (401; public webhook) |

Add new slugs here before using them. Validation `errors[].field` uses
snake_case paths (e.g. `body.registration_number`); `errors[].code` is
snake_case (e.g. `required`, `invalid_invoice_number`).

---

## 5. operationId stems (camelCase)

Shape `{verb}{Resource}` / `{verb}{Resource}ById`. Stable after release. Must
match between OpenAPI, route registration, and `docs/mcp/tools.json`.

| operationId | Resource |
| --- | --- |
| `getHealth` | System |
| `login`, `refreshSession`, `logout`, `getCurrentUser` | Auth |
| `listAuditLogs`, `exportAuditLogs` | Audit (admin oversight) |
| `listOrganizations`, `getOrganizationById`, `createOrganization`, `deleteOrganization` | Organization (superadmin) |
| `listUsers`, `getUserById`, `createUser`, `updateUser`, `deleteUser` | User (admin) |
| `getCompanySettings`, `updateCompanySettings` | Company (issuer profile, per org) |
| `listClients`, `getClientById`, `createClient`, `updateClient`, `deleteClient`, `exportClientsCsv`, `getClientsImportTemplate`, `importClientsCsv` | Client |
| `listItems`, `getItemById`, `createItem`, `updateItem`, `deleteItem`, `exportItemsCsv`, `getItemsImportTemplate`, `importItemsCsv` | Item (品目マスタ) |
| `listTemplates`, `getTemplateById`, `createTemplate`, `updateTemplate`, `deleteTemplate` | Template (雛形) |
| `getDashboard` | Dashboard (unpaid / overdue summary) |
| `listQuotes`, `getQuoteById`, `createQuote`, `changeQuoteStatus`, `getQuotePdf`, `convertQuoteToInvoice`, `exportQuotesCsv` | Quote |
| `listRecurringInvoices`, `getRecurringInvoice`, `createRecurringInvoice`, `updateRecurringInvoice`, `deleteRecurringInvoice` | RecurringInvoice (継続請求, #503) |
| `listInvoices`, `getInvoiceById`, `createInvoice`, `issueInvoice`, `getInvoicePdf`, `generateDownloadToken`, `downloadInvoicePdf`, `sendInvoiceEmail` | Invoice |
| `listPayments`, `recordPayment` | Payment (operator `/admin/*`) |
| `listLineItemSuggestions` | LineItem (history-based suggestions) |
| `listServiceTokens`, `issueServiceToken`, `revokeServiceToken` | ServiceToken (NeNe Clear integration credentials; admin oversight) |

### Service API (`/api/*`, NeNe Clear — ADR 0009)

A **separate** OpenAPI document for the machine consumer; `operationId`s may
reuse operator-surface names within their own doc. New / service-scope stems:

| operationId | Resource |
| --- | --- |
| `listInvoices`, `getInvoiceById` | Invoice (service read; outstanding + payments) |
| `createPayment`, `voidPayment` | Payment (service write; idempotent, `external_reference`) |
| `listClients` | Client (service read; optional) |

Service-token scopes (not human `Capability`): `read:invoices`, `write:payments`.

Extend this list (do not improvise) when adding operations.

---

## 6. Suite federation (cross-repo — NeNe Suite is SSOT)

Federation identifiers are **owned by the NeNe Suite terminology registry** (its
§4–6) and reproduced here **verbatim** (Invoice ADR 0016 conforms to Suite ADR 0012,
accepted 2026-06-19). Spelling must match Suite **exactly**; any divergence is a
merge-blocking error in both repos. These are consumed by Invoice in **suite mode**
only; standalone installs use none of them.

### Membership / environment (`NENE_SUITE_*` — consumed, not minted, by Invoice)

| Term | Canonical | Never |
| --- | --- | --- |
| Membership toggle | `NENE_SUITE_MODE` (**unset / `0` = standalone, `1` = suite**) | `SUITE_MODE`, `NENE2_SUITE_MODE`; string values like `standalone` / `suite` |
| Suite issuer base | `NENE_SUITE_ISSUER_URL` | `ISSUER_URL`, `AUTH_URL` |
| Federation JWKS endpoint | `NENE_SUITE_JWKS_URL` | `JWKS_URL`, `JWK_URI`, `NENE_SUITE_JWK_URL` |
| Suite-minted org UUID | `NENE_SUITE_ORG_EXTERNAL_ID` | `ORG_UUID`, `TENANT_ID`, `NENE_ORG_ID` |
| This app's launcher URL entry | `NENE_SUITE_APP_NENE_INVOICE_URL` | `NENE_INVOICE_URL`, `NENE_SUITE_INVOICE_URL` |
| Sibling-local session secret | `NENE2_LOCAL_JWT_SECRET` (**sibling-generated; the suite does NOT distribute it**) | `JWT_SECRET`, `NENE_JWT_SECRET` |

### Federation org link (Invoice DB column)

| Term | Canonical | Never |
| --- | --- | --- |
| Federation UUID on the org row | `organizations.external_id` (**nullable**) | `org_uuid`, `suite_org_id`, `suite_external_id`, `externalId` |

The local org id stays **`organization_id`** (§3, the immutable billing/numbering
anchor); `external_id` is the **federation link only** and is never the billing
anchor (ADR 0016 §2).

### JWT claims

Two distinct tokens (ADR 0016 §3) — do not conflate their claim sets:

- **Suite federation assertion** (asymmetric, JWKS-verified): `sub`, `org_external_id`,
  `suite_id`, `email`, the suite role claim, `iss`, `aud`, `exp`. It **never** carries
  `org_id`.
- **Invoice local session** (HMAC `NENE2_LOCAL_JWT_SECRET`, ADR 0014): standalone =
  `sub`, `org_id` (local PK); suite mode also mirrors `org_external_id` (and `suite_id`
  if needed). `org_external_id` / `suite_id` are present in **suite mode only**.

| Term | Canonical | Never |
| --- | --- | --- |
| Federation org UUID (claim) | `org_external_id` | `external_id`, `org_uuid`, `suite_org_id` |
| Installation id (claim) | `suite_id` | `install_id`, `NENE_SUITE_ID` (as claim name) |
| Local PK (claim) | `org_id` | — |

**Namespace note:** the claim `org_id` (local PK, **JWT-token namespace**, per the
Suite contract) is distinct from the §3 rule that forbids `org_id` as a **DB column /
JSON field** name (where the canonical column is `organization_id`). The prohibition
in §3 is unaffected.

---

## How to add or change a term

1. Add/rename the entry **here** in the same PR as the code.
2. Update `glossary.md` if it is a product concept; update `naming-conventions.md`
   if it introduces a new pattern.
3. Run the docs-policy and backend-api self-review checklists.
4. Confirm no spelling variant of the term remains anywhere (grep before commit).
