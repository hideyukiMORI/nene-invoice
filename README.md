# NeNe Invoice

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)

**Self-hosted quote and invoice management for Japan SMB — built on [NENE2](https://github.com/hideyukiMORI/NENE2).**

NeNe Invoice is an open-source billing platform: create quotes, issue invoice-compliant PDFs, track payments, and operate everything on infrastructure you control — without a monthly SaaS subscription.

> **Status:** Phase 0 — governance and product design. Runtime scaffold follows in Phase 0+ Issues.

## Goals (summary)

- **Japan invoice compliance** — qualified invoice (適格請求書) fields, tax rates, registration number
- **Self-hosted OSS** — MIT licensed; shared hosting (Tier A) or Docker/VPS (Tier B)
- **Quote-to-cash flow** — estimate → invoice → payment tracking
- **Sibling to NeNe ecosystem** — HTTP integration with Records, Concierge, Corpus; never merged into CMS
- **AI-readable** — OpenAPI contract, MCP for ops, explicit Clean Architecture

## Documentation

| Topic | Document |
| --- | --- |
| **Start here (agents)** | [`AGENTS.md`](./AGENTS.md) |
| **Glossary** | [`docs/explanation/glossary.md`](./docs/explanation/glossary.md) |
| **Product vision** | Issue #2 (in progress) |
| **Naming conventions** | [`docs/development/naming-conventions.md`](./docs/development/naming-conventions.md) |
| **NENE2 inheritance** | [`docs/inheritance-from-nene2.md`](./docs/inheritance-from-nene2.md) |
| **Workflow** | [`docs/workflow.md`](./docs/workflow.md) |
| **Roadmap** | [`docs/roadmap.md`](./docs/roadmap.md) |
| **Current work** | [`docs/todo/current.md`](./docs/todo/current.md) |

## Ecosystem

```
NENE2 (framework)
  ├── NeNe Records   (CMS · optional product catalog upstream)
  ├── NeNe Corpus    (knowledge chat — optional)
  ├── NeNe Concierge (scenario chat · lead capture upstream)
  └── NeNe Invoice   (quote · invoice · payment — this repo)
```

Integration is **HTTP-only**. NeNe Invoice never shares databases with sibling products.

## Contributing

See [`docs/CONTRIBUTING.md`](./docs/CONTRIBUTING.md). All work is Issue-driven; do not commit directly to `main`.

## License

MIT — see [LICENSE](./LICENSE).
