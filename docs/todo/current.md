# Current Work

Last updated: 2026-05-29 (Issue #17)

## Recently merged

- **Issue #1 / PR #2** — Governance and foundation (workflow, naming, ADRs, review checklists, Cursor rules)
- **Issue #3 / PR #8** — Product vision, requirements, domain model ✅ merged
- **Issue #9 / PR #10** — 適格請求書ドキュメント精度修正（端数処理 ADR 0004・登録番号・用語・命名）✅ merged
- **Issue #11 / PR #12** — 日英(ja/en)バイリンガル方針宣言（ADR 0005）・多言語非対象 ✅ merged
- **Issue #13 / PR #14** — 会計・税務コンプライアンス完全順守を拘束ルール化（accounting-compliance.md・compliance チェックリスト）✅ merged
- **Issue #15 / PR #16** — 命名規則の絶対順守・用語レジストリ（唯一の真実）・タイポ厳禁 ✅ merged

## Active

| Issue | Branch | Topic | Status |
| --- | --- | --- | --- |
| #17 | `docs/17-adopt-multi-tenancy` | マルチテナント基盤＋ロール階層（superadmin/admin/member）を土台採用（ADR 0006） | 🔄 PR pending |

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

**Phase 0 — Multi-tenancy adoption: 🔄 in progress** (Issue #17, docs/ADR only)

- ADR 0006: multi-tenant + role hierarchy (superadmin/admin/member/viewer) adopted as foundational
- Tenant-scoped `organization_id` on all billing tables; per-org issuer profile
- Org resolution default `single`; superadmin manages orgs, admin manages users
- terminology registry extended (roles, capabilities, resolution modes, org/user)

**Runtime: not started** — Issue #4 builds the tenant-aware foundation (PR-B) after #17 merges.

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

1. Merge Issue #17 PR (multi-tenancy adoption — docs/ADR)
2. PR-B (Issue #4): tenant-aware runtime foundation — org resolution + JWT auth + RBAC + `GET /health`, mirroring nene-records bootstrap (`RuntimeContainerFactory` → `RuntimeServiceProvider` → `ApplicationServiceProvider`)
3. PR-C+: organization CRUD (superadmin), user CRUD (admin)
