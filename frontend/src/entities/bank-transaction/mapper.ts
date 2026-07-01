import type {
  BankConfirmResultDto,
  BankImportResultDto,
  BankMatchSuggestionDto,
  BankTransactionDto,
  BankTransactionListDto,
} from './api-types'
import { toBankTransactionId } from './ids'
import type {
  BankConfirmResult,
  BankImportResult,
  BankMatchSuggestion,
  BankTransaction,
  BankTransactionPage,
} from './model'

/** Pure DTO → model. Brands the id; normalises optional nulls. */
export function toBankTransaction(dto: BankTransactionDto): BankTransaction {
  return {
    id: toBankTransactionId(dto.id),
    value_date: dto.value_date,
    direction: dto.direction,
    amount_cents: dto.amount_cents,
    payer_name: dto.payer_name ?? null,
    description: dto.description ?? null,
    bank_reference: dto.bank_reference ?? null,
    status: dto.status,
    matched_invoice_id: dto.matched_invoice_id ?? null,
    matched_payment_id: dto.matched_payment_id ?? null,
  }
}

export function toBankTransactionPage(dto: BankTransactionListDto): BankTransactionPage {
  return {
    items: dto.items.map(toBankTransaction),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}

export function toBankMatchSuggestion(dto: BankMatchSuggestionDto): BankMatchSuggestion {
  return {
    invoice_id: dto.invoice_id,
    invoice_number: dto.invoice_number ?? null,
    client_id: dto.client_id,
    client_name: dto.client_name ?? null,
    outstanding_cents: dto.outstanding_cents,
    score: dto.score,
    reasons: dto.reasons,
  }
}

export function toBankImportResult(dto: BankImportResultDto): BankImportResult {
  return {
    imported_count: dto.imported_count,
    skipped_duplicate_count: dto.skipped_duplicate_count,
    row_errors: dto.row_errors.map((e) => ({ line: e.line, reason: e.reason })),
    format_error: dto.format_error,
  }
}

export function toBankConfirmResult(dto: BankConfirmResultDto): BankConfirmResult {
  return {
    transaction: toBankTransaction(dto.transaction),
    payment: {
      id: dto.payment.id,
      invoice_id: dto.payment.invoice_id,
      amount_cents: dto.payment.amount_cents,
      invoice_status: dto.payment.invoice_status,
      total_paid_cents: dto.payment.total_paid_cents,
    },
  }
}
