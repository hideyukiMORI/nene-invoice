/** Shared shape of a template-only CSV import report (ADR 0011). */

export interface CsvImportRowError {
  row: number
  column: string | null
  code: string
  message: string
}

export interface CsvImportReport {
  accepted: boolean
  dry_run: boolean
  format_error: string | null
  summary: { rows: number; created: number; updated: number; errors: number }
  errors: CsvImportRowError[]
}
