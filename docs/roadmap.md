# Roadmap

NeNe Invoice is a self-hosted quote and invoice OSS on NENE2 — Japan SMB billing without SaaS lock-in.

## North Star

Operators can self-host a billing platform that:

- manages clients and company issuer profile
- creates quotes and converts them to invoices
- issues Japan qualified invoice (適格請求書) compliant PDFs
- tracks payment status and overdue items
- runs on **Tier A** shared hosting or **Tier B** Docker/VPS
- optionally integrates with NeNe Records and NeNe Concierge via HTTP

Detailed product scope: [`docs/explanation/product-vision.md`](./explanation/product-vision.md) (Issue #2).

## Phase 0: Governance and Foundation

Goal: engineering discipline before product features grow.

- Governance docs, ADR 0001/0002, inheritance map
- Product vision and requirements documentation
- NENE2 consumer scaffold, `GET /health`, OpenAPI, CI
- Cursor rules and self-review checklists

Tracked by `docs/milestones/2026-05-governance-and-foundation.md`.

**Status: in progress (2026-05-29).**

## Phase 1: Core Billing API

Goal: client master, quotes, invoices, payments — API and DB only.

- `clients`, `quotes`, `invoices`, `line_items`, `payments`, `company_settings` schema
- Japan invoice field validation in UseCases
- Admin JWT auth
- OpenAPI + PHPUnit

## Phase 2: Admin UI + PDF

Goal: operators manage billing without CLI.

- React admin SPA
- Server-side qualified invoice PDF generation
- Email invoice delivery (SMTP)

## Phase 3: Tier A Shared Hosting

Goal: Japan SMB install path.

- Web installer + release ZIP
- Operator documentation
- Same-origin admin on shared hosting

## Phase 4: Ecosystem Integration

Goal: connect to sibling products.

- NeNe Records product catalog import
- NeNe Concierge lead → draft quote webhook
- MCP tool catalog for billing operations

## Non-goals (summary)

See product vision for full list. Short version:

- Not full double-entry accounting
- Not payroll or expense reimbursement
- Not a WordPress plugin
- Not embedded inside NeNe Records
