# Milestone: Governance and Foundation (2026-05)

Goal: establish NeNe Invoice engineering discipline inherited from NENE2 and sibling NeNe products before billing features grow.

**Status: in progress**

> **Workflow note:** Phase 0 governance lands via Issue #1 → branch → PR → merge. All subsequent work uses the same pattern.

## Acceptance Criteria

- [x] GitHub repository created (`hideyukiMORI/nene-invoice`)
- [ ] `docs/inheritance-from-nene2.md` documents local vs upstream rules
- [ ] `docs/workflow.md` and commit conventions in place
- [ ] `AGENTS.md`, `CLAUDE.md`, `docs/CONTRIBUTING.md` exist
- [ ] `.cursor/rules/` summaries for always-on agent guidance
- [ ] `docs/review/` initial self-review checklists
- [ ] ADR 0001 and ADR 0002 accepted
- [ ] `docs/roadmap.md`, `docs/todo/current.md` initialized
- [ ] Product vision documented (Issue #2)
- [ ] `composer check` green on `main` (runtime scaffold — follow-up Issue)

## Follow-up Milestone

Phase 0+ — NENE2 runtime scaffold, health endpoint, OpenAPI stub, CI.

Then Phase 1 — Core billing API (clients, quotes, invoices, payments).
