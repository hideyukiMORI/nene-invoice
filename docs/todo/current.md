# Current Work

Last updated: 2026-05-29 (Issue #37)

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
- **Issue #37** — 組織 CRUD（superadmin・/admin/organizations）⏳ this PR

## Active

| Issue | Branch | Topic | Status |
| --- | --- | --- | --- |
| #37 | `feat/37-organization-crud` | 組織 CRUD（list/get/create/delete・superadmin） | 🔄 PR pending |

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

**Phase 1 — Organization CRUD (superadmin): 🔄 in progress** (Issue #37)

- `GET/POST /admin/organizations`, `GET/DELETE /admin/organizations/{id}` — Handler → UseCase → repo
- Domain exceptions → Problem Details via `OrganizationNotFoundExceptionHandler` (404) / `OrganizationSlugConflictExceptionHandler` (409); wired into EXCEPTION_HANDLERS (no try/catch in handlers)
- Verified live: create 201 / member 403 / dup-slug 409 / missing 422 / list+get 200 / not-found 404 / delete 204
- First real feature guarded by RBAC. Org scoping (`organization_id`) lands with the org-resolution middleware

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

1. User CRUD (admin, `/admin/users`) + org resolution middleware (`single`) + `organization_id` scoping
2. Complete Phase 1 billing core: clients, quotes, invoices, payments
3. Phase 2 admin UI + PDF (minimum for overdue list)
