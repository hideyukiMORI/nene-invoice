/** Lifecycle of a staged bank statement line (自動消込). */
export const BANK_TRANSACTION_STATUSES = ['unmatched', 'matched', 'posted', 'ignored'] as const

export type BankTransactionStatus = (typeof BANK_TRANSACTION_STATUSES)[number]

/** Deposit (入金) vs withdrawal (出金). Only credits are reconciled to invoices. */
export const BANK_DIRECTIONS = ['credit', 'debit'] as const

export type BankDirection = (typeof BANK_DIRECTIONS)[number]

/**
 * CSV column layout presets. `net_bank_credit_debit` = separate 入金/出金 columns;
 * `signed_amount` = one signed amount column. Sent as the `preset` query param.
 */
export const BANK_IMPORT_PRESETS = ['net_bank_credit_debit', 'signed_amount'] as const

export type BankImportPreset = (typeof BANK_IMPORT_PRESETS)[number]
