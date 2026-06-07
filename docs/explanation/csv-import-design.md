# CSV import — design (template-only)

> Governed by [ADR 0011](../adr/0011-csv-import-template-only.md). This document
> specifies the templates, the format gate, the error report, and the
> create/update semantics. **Not yet implemented** — it defines the contract a
> future implementation epic must follow.

Scope: **clients (取引先)** and **items (品目マスタ)** only. Invoices and quotes
are out of scope (numbering / 計上 / qualified-invoice rules).

---

## 1. Principles (recap)

1. Accept **only** a file produced from our template (download → add rows →
   upload). Reject anything else **with a stated reason**.
2. Acceptance is two layers, both giving reasons; the content layer is never
   skipped:
   - **① format gate** (per file) — header + version + encoding.
   - **② content validation** (per row) — the *same* UseCase validation as
     interactive create/update.
3. `id` column: **blank = create, present = update**. Exported CSV round-trips.
4. **All-or-nothing**: validate everything first; on any error, write nothing and
   return a per-row report. Dry-run is the same path.

---

## 2. Format gate (① — per file)

A file is rejected before any row is processed unless **all** hold. Each failure
yields a specific, user-facing reason.

| Check | Rule | Example rejection reason |
| --- | --- | --- |
| Encoding | Must be UTF-8 (BOM optional) | 「ファイルが UTF-8 ではありません。テンプレートを UTF-8 で保存し直してください。」 |
| Version marker | First column is `__template` with value `clients/v1` (or `items/v1`) | 「テンプレートのバージョンが一致しません（期待: clients/v1、検出: なし）。最新テンプレートをダウンロードしてください。」 |
| Header exact match | Header row equals the template header **exactly** (names + order), after the version column | 「不明な列『電話』があります。テンプレート以外の列は追加できません。」 / 「列『登録番号』が見つかりません。」 |
| Row width | Every data row has the same column count as the header | 「8行目: 列数がヘッダーと一致しません。」 |

The `__template` marker is a real column (not a comment line) so Excel does not
mangle it; it pins the version and lets the format evolve (`clients/v2` …).

---

## 3. Templates (v1)

Money is integer yen on screen but stored as **cents**; tax rate is shown as a
**percent** in the sheet and converted to **basis points** internally
(10 → 1000 bps, 8 → 800 bps). Empty optional cells are allowed; required cells
must be non-empty.

### 3.1 clients/v1

| Column | Required | Maps to | Validation |
| --- | --- | --- | --- |
| `__template` | yes | — | literal `clients/v1` |
| `id` | no | `clients.id` | blank = create; if present, an existing **own-org** client id |
| `取引先名` | yes | `name` | non-empty |
| `カナ` | no | `name_kana` | — |
| `担当者` | no | `contact_name` | — |
| `メール` | no | `email` | email format if present |
| `請求先住所` | no | `billing_address` | — |
| `登録番号` | no | `registration_number` | if present, `T` + 13 digits (same rule as CreateClient) |

### 3.2 items/v1

| Column | Required | Maps to | Validation |
| --- | --- | --- | --- |
| `__template` | yes | — | literal `items/v1` |
| `id` | no | `items.id` | blank = create; if present, an existing **own-org** item id |
| `品目名` | yes | `description` | non-empty |
| `標準単価` | yes | `default_unit_price_cents` | non-negative whole-yen integer; stored **1:1 as cents** (JPY's smallest unit is ¥1 — no ×100) |
| `標準税率` | yes | `default_tax_rate_bps` | one of `10` or `8` (percent) → 1000 / 800 bps |

> Note: the **items** export emits the import-template shape (incl. `id` /
> `__template`), so an items export round-trips into an import directly. The
> **clients** export is human-friendly and does **not** yet emit `id` /
> `__template`; extending it for round-trip is a follow-up.

---

## 4. Create / update semantics

- `id` **blank** → **create** a new record (org forced from the holder, ADR 0006).
- `id` **present** → **update** the existing record in the caller's org. For v1
  this is a **full overwrite** of the editable columns: a blank optional cell
  **clears** that field; required columns must be non-empty. (A future v2 may add
  "blank = leave unchanged" semantics if needed.)
- An `id` that does not exist in the caller's org is a **row error** (not a
  silent create), so a typo'd id cannot fork the data.

---

## 5. Content validation & error report (② — per row)

Every row is run through the same validation the interactive UseCase uses; there
is no lenient import path. The whole file is validated first.

- **All valid** → apply all rows in one transaction; return a summary
  (`created`, `updated` counts).
- **Any invalid** → apply **nothing**; return a row-level report.

Error report shape (illustrative):

```json
{
  "accepted": false,
  "summary": { "rows": 50, "created": 0, "updated": 0, "errors": 2 },
  "errors": [
    { "row": 12, "column": "登録番号", "code": "invalid_registration_number",
      "message": "登録番号は T+13桁である必要があります。" },
    { "row": 31, "column": "取引先名", "code": "required",
      "message": "取引先名は必須です。" }
  ]
}
```

`row` is the 1-based spreadsheet row (header = row 1, first data row = row 2) so
the user can jump straight to the cell. Codes reuse the existing
snake_case validation codes (see `docs/explanation/terminology.md` §4).

---

## 6. Endpoints / naming (when implemented)

To be registered in OpenAPI, `OpenApiContractTest`, and the terminology registry
**at implementation time, not before**:

- `getClientsImportTemplate` — `GET /admin/clients/import-template` (download).
- `importClientsCsv` — `POST /admin/clients/import` (multipart upload).
- `getItemsImportTemplate` / `importItemsCsv` — items equivalents.
- A `?dry_run=1` parameter returns the same report without writing.

---

## 7. Out of scope

- Invoices / quotes import (numbering, 計上 timing, qualified-invoice rules).
- Encoding auto-detection / Shift_JIS conversion (rejected with a reason instead).
- Any relaxation of statutory validation. A template that touched statutory
  fields beyond current validation would require 税理士 confirmation (CLAUDE.md).
