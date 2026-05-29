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

Full scope: [`docs/explanation/product-vision.md`](./explanation/product-vision.md), [`docs/explanation/requirements.md`](./explanation/requirements.md).

## Phase 0: Governance and Foundation

Goal: engineering discipline and product design before runtime code.

- Governance docs, ADR 0001/0002, inheritance map ✅
- Product vision, requirements, domain model ✅ (Issue #3)
- NENE2 consumer scaffold, `GET /health`, OpenAPI, CI 🔲 Issues #4–#7
- ADR 0003 dual deployment 🔲 Issue #7

Tracked by `docs/milestones/2026-05-governance-and-foundation.md`.

**Status: product docs complete; runtime scaffold next.**

## Phase 1: Core Billing API

Goal: client master, quotes, invoices, payments — API and DB only.

- `company_settings`, `clients`, `quotes`, `invoices`, `line_items`, `payments`, `document_sequences`
- Japan qualified invoice field validation in UseCases
- Quote → invoice conversion
- Admin JWT auth
- OpenAPI + PHPUnit + PHPStan 8

See [`docs/explanation/requirements.md#phase-1--api-only`](./explanation/requirements.md#phase-1--api-only).

## Phase 2: Admin UI + PDF

Goal: operators manage billing without CLI.

- React admin SPA
- Server-side qualified invoice PDF (Japanese layout)
- Email delivery via SMTP
- Public PDF download token
- Dashboard: unpaid / overdue

## Phase 3: Tier A Shared Hosting

Goal: Japan SMB install path.

- Web installer + release ZIP
- Operator guide (Japanese)
- Same-origin admin on shared hosting

## Phase 4: Ecosystem Integration

Goal: connect to sibling products.

- NeNe Records product catalog import
- NeNe Concierge lead → draft client / quote webhook
- MCP tool catalog
- CSV export; optional payment gateway

## Non-goals

See [`docs/explanation/product-vision.md#non-goals`](./explanation/product-vision.md#non-goals).
