# ADR 0007: Product Identity — NeNe Clear

## Status

accepted

## Context

The repository launched as **NeNe Invoice** (`hideyukiMORI/nene-invoice`) — a
descriptive working title. As the product scope grew (quote-to-cash, payment
reconciliation, dunning, and five post-MVP expansions), the name "Invoice" became
too narrow: it sounds like a PDF generator, not a **billing operations platform**
for humans and AI agents.

Sibling products use short English nouns with layered meaning:

| Product | Name layer |
| --- | --- |
| NeNe Records | archive / registry of typed content |
| NeNe Corpus | body of knowledge |
| NeNe Concierge | guided conversion service |

Alternatives considered:

| Name | Pros | Cons |
| --- | --- | --- |
| **NeNe Clear** | Clearing (消込) + clarity + AI-readable; short | "Clear" can mean delete in UI — use carefully in copy |
| NeNe Collect | Emphasizes receivables / dunning | Sounds like debt collection agency |
| NeNe Receivable | Accurate B2B term (売掛) | Dry; long; narrow for future PO/expense expansions |
| NeNe Settle | Settlement | Generic fintech; less distinctive |
| NeNe Invoice (keep) | Already known; matches repo slug | Undersells reconciliation and quote-to-cash loop |

## Decision

Adopt **NeNe Clear** as the **public product name**.

- **Tagline (EN):** *Clear billing from quote to cash.*
- **Tagline (JA, marketing):** *見積から入金まで、明快に。*

**Repository slug** remains `nene-invoice` until a dedicated rename Issue
(Packagist, redirects, sibling doc links). Code namespace remains `NeneInvoice\`
until a rename ADR — avoid churn before Phase 1 stabilizes.

**Problem Details base URL** remains `https://nene-invoice.dev/problems/` until
domain/branding Issue lands.

Philosophy and ideals: `docs/explanation/philosophy.md` (companion to
`product-vision.md` — *what* we build vs *why* and *how we think*).

## Consequences

**Benefits**

- Name grows with Expansion #1 (消込) without rebranding again.
- Distinct from "invoice SaaS" competitors.
- Fits NeNe naming family (noun, one word, layered meaning).

**Costs**

- Temporary mismatch between display name and repo slug until rename.
- UI copy must not use "Clear" as a verb for delete without context.

**Follow-up**

- Optional: GitHub repo rename to `nene-clear`, namespace migration ADR.
- Register product name in `terminology.md` when identifiers are affected.

## Related

- Philosophy: `docs/explanation/philosophy.md`
- Product vision: `docs/explanation/product-vision.md`
- Expansion roadmap: `docs/explanation/expansion-roadmap.md`
- Issue: #31
