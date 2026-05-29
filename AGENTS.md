# Agent / AI Guide

This file is the entry point for AI agents working on NeNe Invoice.

## Read First

- **Current work & status:** `docs/todo/current.md`
- **Product vision:** `docs/explanation/product-vision.md`
- **Glossary:** `docs/explanation/glossary.md`
- **Naming conventions:** `docs/development/naming-conventions.md`
- Inheritance map: `docs/inheritance-from-nene2.md`
- Human and AI collaboration: `docs/CONTRIBUTING.md`
- Workflow: `docs/workflow.md`
- Coding standards: `docs/development/coding-standards.md`
- Backend standards: `docs/development/backend-standards.md`
- Commit messages: `docs/development/commit-conventions.md`
- AI tool policy: `docs/integrations/ai-tools.md`
- Roadmap: `docs/roadmap.md`
- Milestones: `docs/milestones/2026-05-governance-and-foundation.md`

## Operating Rules

- **Issue-driven**: no substantive code, doc, or config change without a GitHub Issue. Create one first.
- **No direct commits to `main`**. Branch `type/issue-number-summary` → PR → merge after checks.
- **Commits**: Conventional Commits; type/scope English, description/body Japanese, `(#issue)` in subject.
- **Full lifecycle** (unless user limits scope): Issue → branch → implement → verify → commit → push → PR → merge → sync `main`.
- Read NENE2 upstream docs for framework behavior; read local docs for product rules.
- **Never integrate billing into NeNe Records or other sibling repos.** Dependency direction is `NeNe Invoice → upstream APIs`, never the reverse. See ADR 0002.
- Keep `docs/todo/current.md` and milestones aligned with Issues and PRs.
- Keep changes focused. Do not mix governance, feature work, and unrelated cleanup in one PR.
- Do not commit secrets, credentials, local `.env` files, or generated build outputs.
- Prefer explicit, typed, testable code over hidden framework behavior.
- When docs and Cursor rules conflict, update the docs first and keep `.cursor/rules/` concise.

## Project Direction

NeNe Invoice is a self-hosted quote and invoice OSS on NENE2:

- **Primary operators:** Japan SMB on PHP shared hosting (Tier A); **also** Docker/VPS (Tier B). Same codebase — ADR 0003 (planned).
- Create quotes and invoices with Japan invoice system fields (適格請求書).
- Track payment status and overdue reminders.
- Admin UI for clients, line items, documents, and company settings.
- OpenAPI as the contract; MCP for ops/read-write agent tooling on documented HTTP boundaries.
- **Not** full accounting software — sibling to [NeNe Records](https://github.com/hideyukiMORI/nene-records), not a module inside it.

## Framework Reference

Install `hideyukimori/nene2` via Composer. For HTTP runtime, middleware, Problem Details, and MCP patterns, NENE2 upstream documentation is authoritative unless a local ADR says otherwise.
