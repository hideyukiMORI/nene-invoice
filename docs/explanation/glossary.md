# Glossary

Canonical English terms for NeNe Invoice public docs, OpenAPI, and code comments. Use these spellings consistently.

| Term | Definition | Avoid |
| --- | --- | --- |
| **quote** | An estimate sent to a client before work is confirmed; may convert to an invoice | "estimate" in code identifiers (OK in UI copy) |
| **invoice** | A billing document issued to a client; may be a qualified invoice (適格請求書) | "bill" |
| **qualified invoice** | Japan invoice system compliant document with required fields and registration number | "適格請求書" in English API fields |
| **client** | Customer / buyer organization or individual in the billing system | "customer" in code (OK in UI) |
| **line item** | A single row on a quote or invoice (description, quantity, unit price) | "row", "detail line" |
| **payment** | A recorded receipt against an invoice | "receipt" (reserved for PDF noun) |
| **issuer** | The company that issues quotes and invoices (operator's company profile) | "seller" |
| **registration number** | Japan invoice registration number (インボイス登録番号) | "T-number" alone without context |
| **Tier A** | Shared hosting deployment (ZIP + web installer + MySQL) | "rental server tier" |
| **Tier B** | Docker / VPS deployment | "cloud-only" |
| **handler** | HTTP entry point class | "controller" |
| **use case** | Business logic class with `execute()` | "service" (in UseCase sense) |

Expanded definitions will follow in Issue #2 product documentation.
