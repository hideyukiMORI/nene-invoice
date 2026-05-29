# Current Work

Last updated: 2026-05-29 (Issue #74)

## Recently merged

- **Issue #1 / PR #2** — Governance and foundation (workflow, naming, ADRs, review checklists, Cursor rules)
- **Issue #3 / PR #8** — Product vision, requirements, domain model ✅ merged
- **Issue #9 / PR #10** — 適格請求書ドキュメント精度修正（端数処理 ADR 0004・登録番号・用語・命名）✅ merged
- **Issue #11 / PR #12** — 日英(ja/en)バイリンガル方針宣言（ADR 0005）・多言語非対象 ✅ merged
- **Issue #13 / PR #14** — 会計・税務コンプライアンス完全順守を拘束ルール化（accounting-compliance.md・compliance チェックリスト）✅ merged
- **Issue #15 / PR #16** — 命名規則の絶対順守・用語レジストリ（唯一の真実）・タイポ厳禁 ✅ merged
- **Issue #17 / PR #18** — マルチテナント基盤＋ロール階層を土台採用（ADR 0006） ✅ merged
- **Issue #4 / PR #19** — ランタイム基盤: NENE2 consumer scaffold + `GET /health` ✅ merged
- **Issue #20 / PR #21** — Organization 永続化レイヤ（テナント）+ Phinx マイグレーション基盤 ✅ merged
- **Issue #22 / PR #23** — ランタイムに DB 接続（AppConfig）+ DatabaseHealthCheck を配線 ✅ merged
- **Issue #24 / PR #25** — User 永続化 + Role/Capability(RBAC) ドメイン ✅ merged
- **Issue #26 / PR #29** — JWT ログイン（POST /auth/login）+ トークン発行 ✅ merged
- **Issue #30** — Bearer トークンゲート（/admin/ 保護）+ GET /admin/me ✅ merged（commit `f412a52`）
- **Issue #33 / PR #34** — 並行 Cursor 由来 #27/#31 の revert（NeNe Clear 等を除去）✅ merged
- **Issue #35 / PR #36** — CapabilityMiddleware + CapabilityResolver（RBAC 強制）✅ merged
- **Issue #37 / PR #38** — 組織 CRUD（superadmin・/admin/organizations）✅ merged
- **Issue #39 / PR #40** — ユーザー読み取り（/admin/users）+ org スコープ ✅ merged
- **Issue #41 / PR #42** — ユーザー write（create/update/delete）✅ merged
- **Issue #43 / PR #44** — Client（取引先）永続化 + 読み取り ✅ merged
- **Issue #45 / PR #46** — Client write（create/update/delete）✅ merged
- **Issue #47 / PR #48** — 発行者プロフィール（company settings）+ 登録番号検証共有化 ✅ merged
- **Issue #49 / PR #50** — 税計算エンジン（ADR 0004）✅ merged
- **Issue #51 / PR #52** — 監査ログ基盤（ADR 0008）+ Client 統合 ✅ merged
- **Issue #53 / PR #54** — 監査を Organization / User / CompanySettings に展開 ✅ merged
- **Issue #55 / PR #56** — 文書採番（document_sequences）✅ merged
- **Issue #57 / PR #58** — line_items 永続化（quote/invoice 共有）✅ merged
- **Issue #59 / PR #60** — Quote 永続化レイヤ ✅ merged
- **Issue #61 / PR #62** — Quote 作成/一覧/取得（結線）✅ merged
- **Issue #63 / PR #64** — Quote 状態遷移 ✅ merged
- **Issue #65 / PR #66** — Invoice 永続化レイヤ ✅ merged
- **Issue #67 / PR #68** — 見積→請求書変換 + 請求書一覧/取得 ✅ merged
- **Issue #69 / PR #71** — 請求書の発行（INV 採番 + 適格請求書検証）✅ merged
- **Issue #72 / PR #73** — 入金記録（payments）— paid/partially_paid 遷移 ✅ merged
- **Issue #74** — 請求書の直接作成（POST /admin/invoices）⏳ this PR

## Active

| Issue | Branch | Topic | Status |
| --- | --- | --- | --- |
| #74 | `feat/74-invoice-create` | 請求書の直接作成（見積を介さない） | 🔄 PR pending |

## Phase 0+ Backlog

| Issue | Topic | Priority |
| --- | --- | --- |
| #4 | ランタイム基盤: テナント解決＋JWT認証＋RBAC＋`GET /health`（ADR 0006 で拡張） | P0 |
| #5 | OpenAPI stub + MCP catalog | P0 |
| #6 | Backend CI workflow | P0 |
| #7 | ADR 0003 dual deployment (Tier A / Tier B) | P1 |

> マルチテナント前提のため、Issue #4 は単なる health から **テナント解決＋認証＋RBAC を含むランタイム基盤** に拡張。組織/ユーザー CRUD は後続 PR。

## State Summary

**Phase 0 — Governance: ✅ complete**

**Phase 0 — Product design: ✅ complete** (Issue #3 merged)

- Product vision, requirements, domain model documented
- Glossary expanded
- README updated

**Phase 0 — Product design polish: ✅ complete** (Issue #9)

- Tax rounding corrected to once-per-rate-per-document (ADR 0004)
- Registration number documented as syntax-only validation
- `cents` defined as smallest-currency-unit; naming inconsistency fixed

**Phase 0 — Localization scope: ✅ complete** (Issue #11)

- Product localization bound to ja (primary) + en (secondary); multilingual is a non-goal (ADR 0005)
- Statutory invoice content stays Japanese; en applies to operator UI/guides

**Phase 0 — Compliance hardening: ✅ complete** (Issue #13)

- Accounting/tax compliance made binding & non-negotiable (`accounting-compliance.md`)
- Compliance self-review checklist added (`docs/review/compliance.md`)
- Deviations require ADR + tax-professional sign-off

**Phase 0 — Terminology & naming hardening: ✅ complete** (Issue #15)

- Terminology registry added as single source of truth (`terminology.md`)
- Naming made absolute; typos / unregistered terms block merge
- Registry update required in the same PR as any term add/rename

**Phase 0 — Multi-tenancy adoption: ✅ complete** (Issue #17, docs/ADR only)

- ADR 0006: multi-tenant + role hierarchy (superadmin/admin/member/viewer) adopted as foundational
- Tenant-scoped `organization_id` on all billing tables; per-org issuer profile
- Org resolution default `single`; superadmin manages orgs, admin manages users
- terminology registry extended (roles, capabilities, resolution modes, org/user)

**Phase 0+ — Runtime scaffold (PR-B): ✅ complete** (Issue #4)

- NENE2 consumer bootstrap: `composer.json` (require `hideyukimori/nene2 ^1.5`), `public_html/index.php`, `RuntimeContainerFactory` → `RuntimeServiceProvider` → `ApplicationServiceProvider`
- `GET /health` (framework built-in) verified end-to-end; `composer check` green (PHPUnit + PHPStan 8 + php-cs-fixer)

**Phase 1 — Organization persistence: ✅ complete** (Issue #20)

- `organizations` table (Phinx migration + SQLite schema snapshot); `Organization` entity + `PdoOrganizationRepository`
- Phinx wired (`phinx.php`, `composer migrations:*`); `suffix => ''` so SQLite migrate/app share one file
- Repository tested on SQLite `:memory:`

**Phase 1 — Runtime DB connectivity: ✅ complete** (Issue #22)

- `RuntimeServiceProvider` wires ConfigLoader → AppConfig → PdoConnectionFactory → PdoDatabaseQueryExecutor
- `src/Http/DatabaseHealthCheck` (copied from NENE2 reference) registered on `GET /health`
- Verified: DB reachable → 200 `checks.database=ok`; unreachable → 503 `degraded`

**Phase 1 — User identity & RBAC domain: ✅ complete** (Issue #24)

- `Auth/Role` + `Auth/Capability` enums with capability matrix (superadmin/admin/member/viewer)
- `users` table (Phinx + SQLite snapshot); `User` entity + `PdoUserRepository` (org-scoped queries)
- Tested: role matrix + repository on SQLite `:memory:`

**Phase 1 — JWT login: ✅ complete** (Issue #26)

- `POST /auth/login` (public): verifies email+password, issues HMAC bearer token (`LocalBearerTokenVerifier`)
- Handler → `LoginUseCase` → `UserRepository`; token claims `sub`/`role`/`org`

**Phase 1 — Auth gate + current user: ✅ complete** (Issue #30, commit `f412a52`)

- `BearerTokenMiddleware` (prefix `/admin/`) wired into the runtime authMiddleware; `/health`, `/auth/login`, `/` stay public
- `GET /admin/me` returns the authenticated user from token claims (`sub`)
- Verified live: no/invalid token → 401, valid → 200 + user; `/health` public

**Cleanup — revert parallel-agent merges: ✅ complete** (Issue #33 / PR #34)

- Reverted Cursor-authored #27 (expansion-roadmap) and #31 (ADR 0007 NeNe Clear + philosophy)
- NeNe Clear is a *separate* sibling product (入金消込/督促), not this repo — do not reintroduce here

**Phase 1 — RBAC enforcement: ✅ complete** (Issue #35 / PR #36)

- `CapabilityResolver` (path+method → Capability) + `CapabilityMiddleware` (after BearerTokenMiddleware)
- authMiddleware = [BearerTokenMiddleware, CapabilityMiddleware]

**Phase 1 — Organization CRUD (superadmin): ✅ complete** (Issue #37 / PR #38)

- `GET/POST /admin/organizations`, `GET/DELETE /admin/organizations/{id}` — Handler → UseCase → repo
- Domain exceptions → Problem Details (404 / 409) via EXCEPTION_HANDLERS

**Phase 1 — User read + tenant isolation: ✅ complete** (Issue #39 / PR #40)

- `GET /admin/users`, `GET /admin/users/{id}` — admin-only, scoped to caller's org via `Auth/AuthContext`
- Cross-org reads → 404; `password_hash` never serialized
- **Decision:** admin self-service scoped by token `org` claim; URL-addressed OrgResolverMiddleware deferred

**Phase 1 — User write: ✅ complete** (Issue #41 / PR #42)

- `POST/PATCH/DELETE /admin/users` — password hashing, org forced to caller, cross-org → 404, superadmin not assignable (422), self-delete (409), email conflict (409)
- User management complete (CRUD + tenant isolation + escalation prevention)

**Phase 1 — Client persistence + read: ✅ complete** (Issue #43 / PR #44)

- `clients` table with soft delete; `PdoClientRepository` (reads exclude deleted); `GET /admin/clients[/{id}]` org-scoped

**Phase 1 — Client write: ✅ complete** (Issue #45 / PR #46)

- `POST/PATCH/DELETE /admin/clients` — org-scoped, soft delete, buyer `registration_number` T+13 validated

**Phase 1 — Company settings (issuer profile): ✅ complete** (Issue #47 / PR #48)

- `GET/PUT /admin/company-settings` (upsert, one row per org); `Compliance\RegistrationNumber` single home of the T+13 rule

**Phase 1 — Tax calculation engine: ✅ complete** (Issue #49 / PR #50)

- `LineItem\TaxCalculator` (pure, integer-only): round **once per rate** half-up (ADR 0004); subtotal/tax/total + per-rate breakdown

**Phase 1 — Invoice direct create (見積を介さない請求書作成): 🔄 in progress** (Issue #74)

- `POST /admin/invoices` {client_id, line_items[], notes?} — creates a draft invoice directly (no quote)
- Same orchestration as quote create: client in-org validation + per-line checks + `TaxCalculator` (round once per rate, ADR 0004)
- 税率は 10%/8% のみ（§3）; cross-org client / empty lines / bad rate → 422 `validation-failed`
- status `draft`, `invoice_number` null（採番は発行時）, `quote_id` null; lines via `replaceForParent`; `invoice.created` audit
- operationId `createInvoice`（既登録）; reuses `InvoiceValidationException`
- Tested: create (subtotal 2000 / tax 200 / total 2200, 2 lines, no number) / cross-org client→422 / empty→422 / bad rate→422; full DI boot (HealthEndpointTest)

**Phase 1 — Payments (入金記録): ✅ complete** (Issue #72 / PR #73)

- `POST /admin/invoices/{id}/payments` {amount_cents, paid_at?, method?, note?} — records a payment, advances the invoice: partial → `partially_paid`, full → `paid`
- `GET /admin/invoices/{id}/payments` — payment list + running `total_paid_cents`（org スコープ）
- Compliance gates: integer cents only (ADR 0004); only **issued / partially_paid** invoices accept payments (draft / paid → 422 `validation-failed`); non-positive amount → 422; over-payment (recorded total > invoice total) → 422
- `payment.recorded` audit with before/after invoice status (ADR 0008); cross-org → 404
- `payments` table (Phinx + SQLite snapshot) + `Payment` entity + `PdoPaymentRepository`
- Terminology: operationId `recordPayment` registered (was speculative `createPayment`)
- Tested: partial→partially_paid / full→paid / cumulative→paid / over-payment→422 / non-positive→422 / draft→422 / paid→422 / cross-org→404; Pdo repo (sum/order); full DI boot (HealthEndpointTest)

**Phase 1 — Invoice issue (INV 採番 + 適格請求書検証): ✅ complete** (Issue #69 / PR #71)

- `POST /admin/invoices/{id}/issue` {qualified?:bool=true, due_at?} — draft → issued
- Compliance gates (accounting-compliance §2/§4): only a **draft** can be issued (issued docs immutable → 422 `validation-failed`); a **qualified** invoice requires the issuer registration number in company settings (→ 422 `qualified-invoice-incomplete`); no line items → 422
- Allocates `INV-YYYY-NNN` (DocumentNumberGenerator) on issue; sets status/issued_at/due_at/is_qualified_invoice; `invoice.issued` audit (before/after)
- Tested: qualified issue (number assigned, qualified true), non-qualified issue w/o registration, qualified-without-registration → reject, non-draft → reject, no-lines → reject, cross-org → 404; full DI boot verified (HealthEndpointTest)

**Phase 1 — Invoice convert + list/get: ✅ complete** (Issue #67 / PR #68)

- `POST /admin/quotes/{id}/convert` (accepted quote → draft invoice; copies client/totals/line_items, links quote_id, `invoice.created` audit; non-accepted → 422)
- `GET /admin/invoices`, `GET /admin/invoices/{id}` (org-scoped, +line_items)
- Verified live: convert-before-accept 422, accepted→draft invoice (total 2180, 2 lines, no number yet), list/get, audit trail

**Phase 1 — Invoice persistence: ✅ complete** (Issue #65 / PR #66)

- `invoices` table (qualified flag, totals, soft delete, nullable invoice_number until issued, unique org+number) + `Invoice` entity + `InvoiceStatus` enum (draft/issued/partially_paid/paid) + `PdoInvoiceRepository`
- Drafts have no number (multiple NULLs allowed); issue assigns number + qualified flag; org-scoped reads
- Tested on SQLite (draft save, multi-null, issue, list/count, soft delete)

**Phase 1 — Quote status transitions: ✅ complete** (Issue #63 / PR #64)

- `QuoteStatus::canTransitionTo` (draft→sent→accepted/rejected/expired; terminal states blocked)
- `PATCH /admin/quotes/{id}` {status} → 422 `invalid-state-transition` on illegal moves; draft→sent sets issued_at; `quote.status_changed` audit
- Verified live: sent/accepted ok, accepted→rejected 422, garbage 422, audit trail

**Phase 1 — Quote create/list/get: ✅ complete** (Issue #61 / PR #62)

- `POST/GET /admin/quotes`, `GET /admin/quotes/{id}` — create orchestrates client validation + `TaxCalculator` + `DocumentNumberGenerator` (EST-YYYY-NNN) + line items + `quote.created` audit
- Allowed tax rates 10%/8% (accounting-compliance §3); cross-org client / empty lines / bad rate → 422
- Verified live: create 201 (subtotal 2000 / tax 180 / total 2180, EST-2026-001, 2 lines), get/list, audit row
- Next: status transitions (draft→sent→accepted/rejected/expired)

**Phase 1 — Quote persistence: ✅ complete** (Issue #59 / PR #60)

- `quotes` table (totals columns, soft delete, unique org+quote_number) + `Quote` entity + `QuoteStatus` enum + `PdoQuoteRepository`
- Org-scoped reads exclude soft-deleted; tested on SQLite (save/list/count/update/soft-delete)
- Line items stay separate (`line_items`); the create use case orchestrates header + lines + numbering + tax + audit (next PR)

**Phase 1 — Line items persistence: ✅ complete** (Issue #57 / PR #58)

- `line_items` (polymorphic `parent_type`+`parent_id`) + `PdoLineItemRepository` (findByParent ordered / replaceForParent / deleteForParent)
- `LineItem` entity + `LineItemParent` enum; `LineItemResponse` (line_subtotal_cents only, no per-line tax per ADR 0004)
- Tenant scoping via the parent (no org_id on line_items); tested on SQLite (order/replace/isolation/delete)
- Quotes/invoices attach lines via `replaceForParent`

**Phase 1 — Document numbering: ✅ complete** (Issue #55 / PR #56)

- `document_sequences` (org × doc_type × year, unique) + `DocumentNumberGenerator` → `EST-2026-001` / `INV-2026-001`
- Atomic allocation (UPDATE+1 / INSERT fallback on unique conflict); per-org/type/year isolation with yearly reset
- Tested on SQLite; concurrency-safe locking is a documented follow-up (like audit transactionality)
- Quotes/invoices will use this for numbers

**Phase 1 — Audit logging: ✅ foundation + full platform coverage** (Issue #51 / PR #52, Issue #53 / PR #54)

- `audit_logs` + `Audit\AuditRecorder` (ADR 0008): actor / org / action / entity / before & after sanitized snapshots
- **Integrated into every mutating operation**: Client, Organization (create/delete), User (create/update/delete), CompanySettings (upsert → created/updated)
- Verified live: all write actions record rows with the correct actor; `password_hash` never logged
- Limitation (ADR 0008): synchronous best-effort recording, not yet in the mutation's DB transaction (planned)
- Follow-up: audit read endpoint (`GET /admin/audit-logs`); transactional recording

## Handoff Notes

- Namespace: `NeneInvoice\`
- Problem Details base: `https://nene-invoice.dev/problems/`
- **Compliance (binding):** `docs/explanation/accounting-compliance.md` — zero deviations; deviation needs ADR + 税理士 sign-off
- **Terminology (single source of truth):** `docs/explanation/terminology.md` — identifiers match exactly; typos block merge
- **Multi-tenant (binding):** ADR 0006 — `organization_id` on every tenant-scoped table/query; superadmin cross-tenant only; org resolution default `single`
- Roles: `superadmin` (orgs) / `admin` (users + settings) / `member` (billing) / `viewer` (read); reference impl = nene-records `src/Auth/`, `src/Organization/`
- Money: integer cents (smallest currency unit; JPY ¥1 = 1 cent); tax: basis points (1000 = 10%)
- Tax rounding: once per tax rate per document, half-up — ADR 0004
- Qualified invoice: validate `T` + 13 digit registration number at API layer (syntax only)
- UI locale: ja + en only — ADR 0005 (not multilingual)
- Sibling boundary: ADR 0002 — HTTP only

## Next steps

1. Audit read endpoint (`GET /admin/audit-logs`)
2. overdue 表示（issued/partially_paid past due_at）; 請求書 PDF / 帳票（後続フェーズ）
3. OpenAPI stub (Issue #5); Backend CI (Issue #6); ADR 0003 dual deployment (Issue #7)
