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
- Multi-tenancy + role hierarchy adopted as foundational ✅ ADR 0006 (Issue #17)
- NENE2 consumer scaffold (tenant resolution + JWT auth + RBAC + `GET /health`), OpenAPI, CI ✅ Issues #4–#6
- ADR 0003 dual deployment ✅ Issue #7

Tracked by `docs/milestones/2026-05-governance-and-foundation.md`.

**Status: ✅ complete.**

## Phase 1: Core Billing API

Goal: tenancy, auth, client master, quotes, invoices, payments — API and DB only.

- `organizations`, `users`, `company_settings`, `clients`, `quotes`, `invoices`, `line_items`, `payments`, `document_sequences` — all tenant-scoped by `organization_id` (ADR 0006)
- Organization resolution (default `single`) + JWT auth + `Role`/`Capability` RBAC
- Organization CRUD (superadmin); user CRUD (admin)
- Japan qualified invoice field validation in UseCases
- Quote → invoice conversion
- OpenAPI + PHPUnit + PHPStan 8

See [`docs/explanation/requirements.md#phase-1--api-only`](./explanation/requirements.md#phase-1--api-only).

**Status: ✅ complete.**

## Phase 2: Admin UI + PDF

Goal: operators manage billing without CLI.

- React admin SPA — ja + en locale catalogs with an in-app language switcher (ADR 0005; no other locales)
- Server-side qualified invoice PDF (Japanese layout)
- Email delivery via SMTP; public PDF download token
- Dashboard: unpaid / overdue, monthly received, receivable aging
- Audit-log viewer with filters + CSV export (ADR 0008)
- List search / filter / sort (invoices, quotes, clients)
- 案C「高密度オペ」design system, responsive (mobile bottom nav + table-as-cards)

**Status: ✅ complete.**

## Phase 3: Tier A Shared Hosting

Goal: Japan SMB install path.

- Web installer + release ZIP
- Operator guide (Japanese)
- Same-origin admin on shared hosting

**Status: ✅ complete.** (Security assessment rounds 1–2 done; app-layer findings fixed.)

## Phase 4: Ecosystem Integration

Goal: connect to sibling products.

- CSV export (clients, quotes, invoices, payments, audit; list-filter aware; formula-injection safe) ✅
- CSV import (template-only, clients + items) — design first, see ADR 0011 / `explanation/csv-import-design.md`
- NeNe Records product catalog import
- NeNe Concierge lead → draft client / quote webhook
- MCP tool catalog
- Optional payment gateway

**Status: 🔄 in progress.**

## Non-goals

See [`docs/explanation/product-vision.md#non-goals`](./explanation/product-vision.md#non-goals).
