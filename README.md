# NeNe Invoice

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://www.php.net/)

**Quote, invoice, and payment tracking for Japan SMB — self-hosted on your stack.**

NeNe Invoice is an open-source billing platform built on [NENE2](https://github.com/hideyukiMORI/NENE2). Create quotes, issue **qualified invoice** (適格請求書) PDFs, track payments, and run everything on shared hosting or Docker — without a monthly SaaS fee.

> **Example operator:** an office manager on shared hosting issues invoice-compliant PDFs from admin UI instead of Excel + manual PDF — see [`docs/explanation/product-vision.md`](./docs/explanation/product-vision.md#primary-persona).

## Goals

- **Japan invoice compliance** — registration number, tax rates, qualified invoice PDF fields
- **Self-hosted OSS** — MIT licensed; Tier A shared hosting or Tier B Docker/VPS
- **Quote-to-cash** — estimate → invoice → payment in one product
- **Multi-tenant from the foundation** — superadmin manages organizations, admin manages users; single-SMB installs run in `single` mode ([ADR 0006](./docs/adr/0006-multi-tenancy-and-roles.md))
- **Sibling to NeNe ecosystem** — optional HTTP link to Records / Concierge; never merged into CMS
- **AI-readable** — OpenAPI contract, MCP for ops, explicit Clean Architecture
- **Bilingual, not multilingual** — Japanese + English admin UI for operators (incl. non-Japanese running businesses in Japan); other locales are a deliberate non-goal ([ADR 0005](./docs/adr/0005-bilingual-ja-en-scope.md))

## Quick Start

**Status:** Phases 0–3 shipped — multi-tenant billing API, React admin UI, qualified-invoice PDF, payments, audit logging, bilingual (ja/en) UI with a language switcher, and list search / filter / sort are all in place, plus the Tier A shared-hosting install path and two security-assessment rounds. Phase 4 (sibling-product integration) is in progress: CSV export, service-token management, optional hosted card payments (PAY.JP — pay links, webhook, gateway settings; [ADR 0013](./docs/adr/0013-launch-payment-gateway-payjp.md)), and silent re-authentication via an httpOnly refresh cookie ([ADR 0014](./docs/adr/0014-auth-session-persistence-refresh-cookie.md)) have landed. **Recurring billing** (定期請求 — schedule → auto-draft generation, `/recurring`) is running end-to-end including its execution route (#526), and **bank-transfer auto-reconciliation** (`/bank-reconciliation`, #505 — CSV import → payer-alias matching → confirm-to-record) is in (only fee write-off / over-payment split remains, tax-advisor gated). **Type-B multi-tenancy** (superadmin org provisioning + a consultant SPA served under `/{slug}/`) and the **NENE2 install-toolkit consumer** refactor (#562, now on NENE2 `^1.6`) have landed. A contract-verified upstream link to **NeNe Clear** (reconciliation / dunning) is live; **MFA (standalone TOTP)** is designed ([`docs/design/mfa-totp.md`](./docs/design/mfa-totp.md), conforming to Suite ADR 0025). Managed-cloud delivery is provided by **NeNe Suite** (free-trial / VPS-migration / paid-guarantee). See [`docs/handover/2026-07-03-typeb-phase2-complete-and-cleanup.md`](./docs/handover/2026-07-03-typeb-phase2-complete-and-cleanup.md).

### Option A — Docker (recommended, fastest)

No PHP or Node needed on the host. One command brings up the API, the built admin UI, MySQL, and Mailpit; migrations and dev seed data run automatically.

```bash
git clone https://github.com/hideyukiMORI/nene-invoice.git
cd nene-invoice
docker compose up -d --build

# API + admin UI: http://localhost:8510   (sign in: admin@example.com / password123)
# Mailpit inbox:  http://localhost:8585
# phpMyAdmin:     http://localhost:8581
curl http://localhost:8510/health        # {"status":"ok","checks":{"database":"ok"}}
```

The admin SPA is baked into the image. After editing frontend code run `docker compose build app`, or use the host Vite dev server (Option B) for HMR. Ports are fixed to the `85**` range in `compose.yaml`; override via `NENE_INVOICE_*` in `.env`.

### Option B — Host (PHP + Node on your machine)

For active development with live reload (SQLite by default).

```bash
composer install
composer check                          # PHPUnit + PHPStan 8 + php-cs-fixer
php tools/seed-dev.php                   # dev users + sample data (admin@example.com / password123)

# Backend API (front controller as router; local ports fixed to the 85** range)
php -S localhost:8510 -t public_html public_html/index.php
curl http://localhost:8510/health       # {"status":"ok","checks":{"database":"ok"}}

# Admin SPA (separate terminal) — Vite dev server on :5185
cd frontend && npm install && npm run dev
```

> SQLite dev needs an absolute `DB_NAME` path in `.env` (the built-in server's cwd is the docroot). For mail, run host Mailpit: `docker compose up -d mailpit`.

Shared-hosting / production install uses the web installer (`public_html/install.php`) and a release ZIP — see [`docs/operator-guide-ja.md`](./docs/operator-guide-ja.md).

## Architecture

```
Admin UI (React SPA)  ──→  NeNe Invoice API (NENE2 / PHP 8.4)  ──→  MySQL / SQLite
Ops / MCP             ──→            │  ▲
NeNe Clear ──HTTP /api/*─────────────┘  │   (reconciliation / dunning — Invoice = billing SSOT)
                                     ↓ HTTP (optional)
                          NeNe Records / NeNe Concierge
```

Managed-cloud delivery is orchestrated by **NeNe Suite** (federation IdP + installer); Invoice's
path into it is the `organizations.external_id` federation link (#492) and the federation epic.

- **Backend**: PHP 8.4, NENE2, Handler → UseCase → Repository (org-scoped, ADR 0006)
- **Money**: integer cents everywhere — no floats; tax rounded once per rate (ADR 0004)
- **PDF**: server-side qualified-invoice generation (mPDF, Japanese fonts)
- **Audit**: every mutating operation recorded with before/after snapshots (ADR 0008)
- **Deploy**: Tier A (shared-hosting installer + release ZIP) shipped; Tier B (Docker) per ADR 0003

## Current Status

| Phase | Scope | Status |
| --- | --- | --- |
| 0 | Governance + product docs | ✅ |
| 1 | Core billing API — auth, multi-tenancy, clients, quotes, invoices, payments | ✅ |
| 2 | Admin UI (React) + qualified-invoice PDF + dashboard + audit log + ja/en | ✅ |
| 3 | Tier A shared hosting — installer, release ZIP, operator guide | ✅ |
| Sec | Security assessment rounds 1–2 (findings fixed) | ✅ |
| 4 | Ecosystem integration — CSV export ✅, service tokens ✅, PAY.JP ✅, refresh-cookie auth ✅, **recurring billing** (`/recurring`, execution route #526) ✅, **bank-transfer auto-reconciliation** (`/bank-reconciliation`, #505) ✅ (only fee write-off / over-payment split #543 gated), **type-B multi-tenancy** (superadmin provisioning + per-slug consultant SPA) ✅, **NENE2 install-toolkit consumer** (#562, NENE2 ^1.6) ✅, live **NeNe Clear** link ✅, **MFA design** ✅ (impl pending) | 🔄 In progress |

See [`docs/roadmap.md`](./docs/roadmap.md) and [`docs/todo/current.md`](./docs/todo/current.md).

## Non-goals

- Not full accounting / general ledger
- Not payroll or expense reimbursement
- Not inventory or POS
- Not a WordPress plugin
- Not embedded inside NeNe Records

Full list: [`docs/explanation/product-vision.md#non-goals`](./docs/explanation/product-vision.md#non-goals)

## Documentation

| Topic | Document |
| --- | --- |
| **Compliance (binding)** | [`docs/explanation/accounting-compliance.md`](./docs/explanation/accounting-compliance.md) |
| **Product vision** | [`docs/explanation/product-vision.md`](./docs/explanation/product-vision.md) |
| **Requirements** | [`docs/explanation/requirements.md`](./docs/explanation/requirements.md) |
| **Domain model** | [`docs/explanation/domain-model.md`](./docs/explanation/domain-model.md) |
| **Glossary** | [`docs/explanation/glossary.md`](./docs/explanation/glossary.md) |
| **Terminology registry** | [`docs/explanation/terminology.md`](./docs/explanation/terminology.md) |
| **Start here (agents)** | [`AGENTS.md`](./AGENTS.md) |
| **Workflow** | [`docs/workflow.md`](./docs/workflow.md) |

## Ecosystem

Part of the [hideyukiMORI NeNe portfolio](https://github.com/hideyukiMORI):

| Product | Role |
| --- | --- |
| [NENE2](https://github.com/hideyukiMORI/NENE2) | Framework runtime |
| [nene-records](https://github.com/hideyukiMORI/nene-records) | CMS · optional product catalog |
| [nene-corpus](https://github.com/hideyukiMORI/nene-corpus) | Knowledge chat |
| [nene-concierge](https://github.com/hideyukiMORI/nene-concierge) | Scenario chat · optional leads |
| [nene-clear](https://github.com/hideyukiMORI/nene-clear) | Reconciliation · dunning — downstream consumer of this billing SSOT |
| [nene-suite](https://github.com/hideyukiMORI/nene-suite) | Multi-app installer · federation IdP · managed cloud |
| **nene-invoice** | Quote · invoice · payment — financial-cluster foundation (this repo) |

## Contributing

Issue-driven development — see [`docs/CONTRIBUTING.md`](./docs/CONTRIBUTING.md).

## License

MIT — see [LICENSE](./LICENSE).
