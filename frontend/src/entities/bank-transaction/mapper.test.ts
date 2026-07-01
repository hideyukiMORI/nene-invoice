import { describe, expect, it } from 'vitest'
import type {
  BankConfirmResultDto,
  BankImportResultDto,
  BankMatchSuggestionDto,
  BankTransactionDto,
} from './api-types'
import {
  toBankConfirmResult,
  toBankImportResult,
  toBankMatchSuggestion,
  toBankTransaction,
  toBankTransactionPage,
} from './mapper'

const dto: BankTransactionDto = {
  id: 42,
  value_date: '2026-06-30',
  direction: 'credit',
  amount_cents: 110000,
  status: 'unmatched',
}

describe('toBankTransaction', () => {
  it('brands the id and normalises optional fields to null', () => {
    const tx = toBankTransaction(dto)
    expect(tx.id).toBe(42)
    expect(tx.direction).toBe('credit')
    expect(tx.amount_cents).toBe(110000)
    expect(tx.value_date).toBe('2026-06-30')
    expect(tx.payer_name).toBeNull()
    expect(tx.description).toBeNull()
    expect(tx.bank_reference).toBeNull()
    expect(tx.matched_invoice_id).toBeNull()
    expect(tx.matched_payment_id).toBeNull()
  })
})

describe('toBankTransactionPage', () => {
  it('maps items and pagination', () => {
    const page = toBankTransactionPage({ items: [dto], total: 1, limit: 50, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(42)
    expect(page.total).toBe(1)
  })
})

describe('toBankMatchSuggestion', () => {
  it('maps the scored candidate, normalising optional names', () => {
    const suggestionDto: BankMatchSuggestionDto = {
      invoice_id: 10,
      client_id: 5,
      outstanding_cents: 110000,
      score: 92,
      reasons: ['amount_exact'],
    }
    const suggestion = toBankMatchSuggestion(suggestionDto)
    expect(suggestion.invoice_id).toBe(10)
    expect(suggestion.invoice_number).toBeNull()
    expect(suggestion.client_name).toBeNull()
    expect(suggestion.score).toBe(92)
    expect(suggestion.reasons).toEqual(['amount_exact'])
  })
})

describe('toBankImportResult', () => {
  it('maps counts and row errors', () => {
    const importDto: BankImportResultDto = {
      imported_count: 3,
      skipped_duplicate_count: 1,
      row_errors: [{ line: 4, reason: 'invalid amount' }],
      format_error: null,
    }
    const result = toBankImportResult(importDto)
    expect(result.imported_count).toBe(3)
    expect(result.skipped_duplicate_count).toBe(1)
    expect(result.row_errors[0]?.line).toBe(4)
    expect(result.format_error).toBeNull()
  })
})

describe('toBankConfirmResult', () => {
  it('maps the posted line and the recorded payment', () => {
    const confirmDto: BankConfirmResultDto = {
      transaction: { ...dto, status: 'posted', matched_invoice_id: 10 },
      payment: {
        id: 7,
        invoice_id: 10,
        amount_cents: 110000,
        invoice_status: 'paid',
        total_paid_cents: 110000,
      },
    }
    const result = toBankConfirmResult(confirmDto)
    expect(result.transaction.status).toBe('posted')
    expect(result.payment.invoice_status).toBe('paid')
    expect(result.payment.amount_cents).toBe(110000)
  })
})
