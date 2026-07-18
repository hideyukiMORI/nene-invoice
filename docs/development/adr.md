# ADR Policy

NeNe Invoice uses lightweight Architecture Decision Records, inherited from [NENE2 ADR policy](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/adr.md).

## When to write an ADR

Write an ADR when a decision affects:

- quote, invoice, payment architecture, or public API contracts
- Japan invoice compliance strategy or PDF generation approach
- dependency or framework integration choices
- authentication, authorization, or MCP safety boundaries
- dual deployment (Tier A shared hosting vs Tier B Docker)
- release, versioning, or compatibility policy
- long-term maintenance cost

Do **not** use ADRs for routine task detail — use Issues, PRs, and the private `internal-docs/invoice/todo/current.md`.

## Directory and naming

```text
docs/adr/
├── 0000-template.md
├── 0001-inherit-nene2-governance.md
├── 0002-separate-from-sibling-products.md
└── NNNN-short-title.md
```

Use a stable four-digit sequence. Do not renumber after publication.

## Status values

- `proposed`
- `accepted`
- `deprecated`
- `superseded`

Supersede old ADRs instead of silently rewriting history.

## Template

Use `docs/adr/0000-template.md`.

## Relationship to NENE2 ADRs

- NENE2 ADRs describe **framework** decisions.
- NeNe Invoice ADRs describe **product** decisions.
- When consuming NENE2, prefer upstream ADRs for framework behavior; record local deviations here.
