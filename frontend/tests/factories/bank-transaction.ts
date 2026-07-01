import type { components } from '@/shared/api/schema.gen'

type BankTransactionDto = components['schemas']['BankTransaction']
type BankMatchSuggestionDto = components['schemas']['BankMatchSuggestion']
type BankImportResultDto = components['schemas']['BankImportResult']
type BankConfirmResultDto = components['schemas']['BankConfirmResult']

export function buildBankTransactionDto(
  overrides: Partial<BankTransactionDto> = {},
): BankTransactionDto {
  return {
    id: 42,
    value_date: '2026-06-30',
    direction: 'credit',
    amount_cents: 110000,
    payer_name: 'カ）トリヒキサキ',
    description: '振込',
    bank_reference: 'REF-001',
    status: 'unmatched',
    matched_invoice_id: null,
    matched_payment_id: null,
    imported_at: '2026-06-30 09:00:00',
    created_at: '2026-06-30 09:00:00',
    updated_at: '2026-06-30 09:00:00',
    ...overrides,
  }
}

export function buildBankMatchSuggestionDto(
  overrides: Partial<BankMatchSuggestionDto> = {},
): BankMatchSuggestionDto {
  return {
    invoice_id: 10,
    invoice_number: 'INV-2026-010',
    client_id: 5,
    client_name: '得意先ABC',
    outstanding_cents: 110000,
    score: 92,
    reasons: ['amount_exact', 'name_match'],
    ...overrides,
  }
}

export function buildBankImportResultDto(
  overrides: Partial<BankImportResultDto> = {},
): BankImportResultDto {
  return {
    imported_count: 3,
    skipped_duplicate_count: 1,
    row_errors: [],
    format_error: null,
    ...overrides,
  }
}

export function buildBankConfirmResultDto(
  overrides: Partial<BankConfirmResultDto> = {},
): BankConfirmResultDto {
  return {
    transaction: buildBankTransactionDto({ status: 'posted', matched_invoice_id: 10 }),
    payment: {
      id: 7,
      invoice_id: 10,
      amount_cents: 110000,
      invoice_status: 'paid',
      total_paid_cents: 110000,
    },
    ...overrides,
  }
}
