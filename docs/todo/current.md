# Current Work

Last updated: 2026-05-29 (Issue #3)

## Recently merged

- **Issue #1 / PR #2** — Governance and foundation (workflow, naming, ADRs, review checklists, Cursor rules)
- **Issue #3** — Product vision, requirements, domain model (PR pending)

## Active

| Issue | Branch | Topic | Status |
| --- | --- | --- | --- |
| #3 | `docs/3-product-vision` | Product vision and requirements | 🔄 PR pending |

## Phase 0+ Backlog

| Issue | Topic | Priority |
| --- | --- | --- |
| #4 | NENE2 runtime scaffold + health endpoint | P0 |
| #5 | OpenAPI stub + MCP catalog | P0 |
| #6 | Backend CI workflow | P0 |
| #7 | ADR 0003 dual deployment (Tier A / Tier B) | P1 |

## State Summary

**Phase 0 — Governance: ✅ complete**

**Phase 0 — Product design: 🔄 merging**

- Product vision, requirements, domain model documented
- Glossary expanded
- README updated

**Runtime: not started** — begin with Issue #4 after #3 merges.

## Handoff Notes

- Namespace: `NeneInvoice\`
- Problem Details base: `https://nene-invoice.dev/problems/`
- Money: integer cents; tax: basis points (1000 = 10%)
- Qualified invoice: validate `T` + 13 digit registration number at API layer
- Sibling boundary: ADR 0002 — HTTP only

## Next steps

1. Merge Issue #3 PR
2. Start Issue #4 (runtime scaffold) on branch `feat/4-runtime-scaffold`
3. Issue #5–#6 can follow in parallel after #4 lands
