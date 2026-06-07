# ADR 0011: CSV import is template-only, fully re-validated, with stated reasons

## Status

proposed

## Context

We ship CSV **export** for clients, quotes, invoices, payments, and the audit log
(#378 / #379 / #382). The next request is CSV **import** — bulk-loading master
data, primarily clients (取引先) and items (品目マスタ), e.g. when onboarding or
migrating from another tool.

Import is fundamentally harder and riskier than export, and the risk is partly
a **compliance** risk (binding per CLAUDE.md and
`docs/explanation/accounting-compliance.md`):

- **Content validity is statutory.** A client's `registration_number` must be
  `T` + 13 digits; a bad value flows into qualified-invoice PDFs. Import must not
  weaken the validation that `CreateClient` / `CreateItem` already enforce.
- **Arbitrary-file parsing is unbounded.** Column mapping, column order, extra
  or missing columns, header synonyms, and locale-specific Excel quirks make
  "intelligently interpret any CSV" an open-ended, error-prone problem.
- **Encoding.** Excel on Windows frequently re-saves CSV as Shift_JIS; silently
  guessing the encoding risks corrupting names/addresses.
- **Failure semantics & dedup.** What happens on a partially-invalid file? How do
  we tell "create a new record" from "update an existing one"?

Trying to accept any CSV multiplies all of the above. We would rather constrain
the input than build a fragile heuristic importer.

## Decision

Adopt a **template-only** import model with explicit, stated rejection reasons.

1. **Template-only intake.** The system accepts only files that match a template
   it distributes. The user must **download the template, add rows to that exact
   structure, and upload it**. Anything else is rejected.

2. **Two-layer acceptance, both with stated reasons** — and the content layer is
   never bypassed:
   - **① Format gate (per file).** Reject unless the header row matches the
     template **exactly** (column names and order), the template **version**
     matches, and the file is **UTF-8**. The rejection message names the precise
     cause (e.g. unknown column `電話`, wrong/missing version, non-UTF-8).
   - **② Content validation (per row).** Every row is validated through the
     **same UseCase** as interactive creation/update (`CreateClient` /
     `CreateItem` rules, including `registration_number` = T+13 digits). No
     import-only "lenient" path exists. Each invalid row is reported with its
     line number and reason.

3. **`id` column drives create vs. update.** The template includes an `id`
   column: **blank = create, present = update an existing record** (scoped to the
   caller's organization, ADR 0006). Because exports already carry the `id`, an
   exported CSV round-trips: export → edit → import updates in place; new rows
   leave `id` blank. For v1, an update is a **full overwrite** of the editable
   columns (a blank optional cell clears that field); required columns must be
   non-empty.

4. **All-or-nothing with a row-level error report.** Validate the whole file
   first; if **any** row fails, write **nothing** and return a per-row error
   report (a dry-run preview is the same code path). This keeps the master data
   consistent and makes fixes obvious.

5. **UTF-8 only.** The template is UTF-8 (BOM). Non-UTF-8 input is rejected with
   an explicit "save as UTF-8" reason plus help, rather than guessed.

6. **Audited.** Bulk create/update is recorded in the audit log (ADR 0008).

7. **Scope.** Import covers **clients (取引先) and items (品目マスタ) only.**
   Invoices and quotes are **out of scope** — they involve numbering (採番),
   revenue timing (計上), and qualified-invoice rules that must not be bulk-loaded
   from a spreadsheet.

## Consequences

**Benefits**
- The input space is **bounded and verifiable**: we accept exactly one known
  shape and re-validate every value, so import cannot become a backdoor around
  the compliance rules.
- **Export ↔ import round-trip** via the shared `id` column gives a natural
  edit-in-Excel workflow and removes the dedup/matching ambiguity.
- Failures are **legible**: the user always gets a concrete reason (file-level or
  row-level), satisfying the "state the reason" policy.

**Costs / trade-offs**
- Users **must** start from our template; free-form CSVs are rejected. This is a
  deliberate friction trade for safety and is mitigated by a one-click template
  download and clear messaging.
- Encoding friction is reduced but not eliminated (Excel re-saving); we mitigate
  with explicit rejection + help, not silent conversion.
- Template **versioning** is required so the format can evolve (a `__template`
  marker column pins `clients/v1`, `items/v1`).

**Follow-up work** (separate implementation epic, governed by this ADR)
- Template definitions, format-gate spec, and error-report shape:
  `docs/explanation/csv-import-design.md`.
- New endpoints/operationIds (e.g. `importClientsCsv`, `importItemsCsv`,
  `getClientsImportTemplate`) to be added to the OpenAPI spec, the contract test,
  and the terminology registry **when implemented** (not before).
- Decide the v2 question of partial-update ("blank = clear" vs "blank = leave
  unchanged") if the full-overwrite v1 semantics prove insufficient.
- No tax-rule change is introduced; import reuses existing validation. If a
  future template touches statutory fields beyond what `CreateClient` /
  `CreateItem` already validate, that change needs 税理士 confirmation per
  CLAUDE.md.

## Related

- Issue: `#384`
- PR: `#000`
- Supersedes: none
- Superseded by: none
