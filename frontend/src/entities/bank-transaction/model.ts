import type { BankDirection, BankImportPreset, BankTransactionStatus } from './enum'
import type { BankTransactionId } from './ids'

/**
 * UI read model for a staged bank statement line (自動消込). Field names mirror the
 * API (snake_case, per product rule) with a branded id and narrowed enums. Money
 * stays integer cents — never floats.
 */
export interface BankTransaction {
  id: BankTransactionId
  /** Value date (YYYY-MM-DD, JST calendar date). */
  value_date: string
  direction: BankDirection
  amount_cents: number
  payer_name: string | null
  description: string | null
  bank_reference: string | null
  status: BankTransactionStatus
  matched_invoice_id: number | null
  matched_payment_id: number | null
}

export interface BankTransactionPage {
  items: BankTransaction[]
  total: number
  limit: number
  offset: number
}

/** A scored invoice candidate for a staged deposit (advice only — no auto-post). */
export interface BankMatchSuggestion {
  invoice_id: number
  invoice_number: string | null
  client_id: number
  client_name: string | null
  outstanding_cents: number
  /** Match score (higher = stronger). */
  score: number
  /** Human-readable reasons the candidate scored (amount / name / date …). */
  reasons: string[]
}

/** Outcome of a CSV import. A non-null `format_error` means nothing was staged. */
export interface BankImportResult {
  imported_count: number
  skipped_duplicate_count: number
  row_errors: { line: number; reason: string }[]
  format_error: string | null
}

/** Outcome of confirming a match — the posted line plus the recorded payment. */
export interface BankConfirmResult {
  transaction: BankTransaction
  payment: {
    id: number
    invoice_id: number
    amount_cents: number
    invoice_status: 'issued' | 'partially_paid' | 'paid'
    total_paid_cents: number
  }
}

/** Raw file bytes plus the chosen column layout, as submitted on import. */
export interface ImportBankCsvInput {
  /** The picked file, sent as raw bytes (Shift_JIS-safe — never decoded to text). */
  file: Blob
  preset: BankImportPreset
}

/** Confirm a staged deposit against a chosen invoice. */
export interface ConfirmBankMatchInput {
  id: BankTransactionId
  invoice_id: number
}
