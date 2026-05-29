# Current Work

Last updated: 2026-05-29

## Active

| Issue | Branch | Topic | Status |
| --- | --- | --- | --- |
| #1 | `docs/1-governance-foundation` | Governance and foundation docs | 🔄 PR pending |
| #2 | — | Product vision and requirements | 🔲 next |

## Phase 0 Backlog (Issues to create after #1 merges)

| Priority | Topic | Notes |
| --- | --- | --- |
| P0 | NENE2 runtime scaffold + health endpoint | composer.json, docker compose, GET /health |
| P0 | OpenAPI stub + MCP catalog | docs/openapi/openapi.yaml, docs/mcp/tools.json |
| P0 | Backend CI workflow | GitHub Actions — PHPUnit, PHPStan, OpenAPI |
| P1 | ADR 0003 dual deployment strategy | Tier A / Tier B — follow Corpus/Concierge pattern |

## State Summary

**Phase 0 — Governance: in progress**

- GitHub repository created: `hideyukiMORI/nene-invoice`
- Issue #1: governance initialization
- Issue #2: product vision (queued)

No runtime code yet. `composer check` lands with scaffold Issue.

## Handoff Notes

- Namespace: `NeneInvoice\`
- Problem Details base: `https://nene-invoice.dev/problems/`
- Money: integer cents everywhere
- Sibling boundary: ADR 0002 — HTTP only, no shared DB
