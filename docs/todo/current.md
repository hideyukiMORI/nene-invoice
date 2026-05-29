# Current Work

Last updated: 2026-05-29 (Issue #13)

## Recently merged

- **Issue #1 / PR #2** — Governance and foundation (workflow, naming, ADRs, review checklists, Cursor rules)
- **Issue #3 / PR #8** — Product vision, requirements, domain model ✅ merged
- **Issue #9 / PR #10** — 適格請求書ドキュメント精度修正（端数処理 ADR 0004・登録番号・用語・命名）✅ merged
- **Issue #11 / PR #12** — 日英(ja/en)バイリンガル方針宣言（ADR 0005）・多言語非対象 ✅ merged

## Active

| Issue | Branch | Topic | Status |
| --- | --- | --- | --- |
| #13 | `docs/13-accounting-compliance-binding` | 会計・税務コンプライアンス完全順守を拘束ルール化（accounting-compliance.md・compliance チェックリスト） | 🔄 PR pending |

## Phase 0+ Backlog

| Issue | Topic | Priority |
| --- | --- | --- |
| #4 | NENE2 runtime scaffold + health endpoint | P0 |
| #5 | OpenAPI stub + MCP catalog | P0 |
| #6 | Backend CI workflow | P0 |
| #7 | ADR 0003 dual deployment (Tier A / Tier B) | P1 |

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

**Phase 0 — Compliance hardening: 🔄 in progress** (Issue #13)

- Accounting/tax compliance made binding & non-negotiable (`accounting-compliance.md`)
- Compliance self-review checklist added (`docs/review/compliance.md`)
- Deviations require ADR + tax-professional sign-off

**Runtime: not started** — begin with Issue #4.

## Handoff Notes

- Namespace: `NeneInvoice\`
- Problem Details base: `https://nene-invoice.dev/problems/`
- **Compliance (binding):** `docs/explanation/accounting-compliance.md` — zero deviations; deviation needs ADR + 税理士 sign-off
- Money: integer cents (smallest currency unit; JPY ¥1 = 1 cent); tax: basis points (1000 = 10%)
- Tax rounding: once per tax rate per document, half-up — ADR 0004
- Qualified invoice: validate `T` + 13 digit registration number at API layer (syntax only)
- UI locale: ja + en only — ADR 0005 (not multilingual)
- Sibling boundary: ADR 0002 — HTTP only

## Next steps

1. Merge Issue #13 PR (compliance hardening)
2. Start Issue #4 (runtime scaffold) on branch `feat/4-runtime-scaffold`
3. Issue #5–#6 can follow in parallel after #4 lands
