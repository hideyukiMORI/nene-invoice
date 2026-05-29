# Current Work

Last updated: 2026-05-29 (Issue #3)

## Recently merged

- **Issue #1 / PR #2** — Governance and foundation (workflow, naming, ADRs, review checklists, Cursor rules)
- **Issue #3 / PR #8** — Product vision, requirements, domain model ✅ merged

## Active

| Issue | Branch | Topic | Status |
| --- | --- | --- | --- |
| #9 | `docs/9-invoice-compliance-accuracy` | 適格請求書ドキュメント精度修正（端数処理 ADR 0004・登録番号・用語・命名） | 🔄 PR pending |

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

**Phase 0 — Product design polish: 🔄 in progress** (Issue #9)

- Tax rounding corrected to once-per-rate-per-document (ADR 0004)
- Registration number documented as syntax-only validation
- `cents` defined as smallest-currency-unit; naming inconsistency fixed

**Runtime: not started** — begin with Issue #4.

## Handoff Notes

- Namespace: `NeneInvoice\`
- Problem Details base: `https://nene-invoice.dev/problems/`
- Money: integer cents (smallest currency unit; JPY ¥1 = 1 cent); tax: basis points (1000 = 10%)
- Tax rounding: once per tax rate per document, half-up — ADR 0004
- Qualified invoice: validate `T` + 13 digit registration number at API layer (syntax only)
- Sibling boundary: ADR 0002 — HTTP only

## Next steps

1. Merge Issue #9 PR (product design polish)
2. Start Issue #4 (runtime scaffold) on branch `feat/4-runtime-scaffold`
3. Issue #5–#6 can follow in parallel after #4 lands
