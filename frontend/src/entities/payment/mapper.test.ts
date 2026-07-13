import { describe, expect, it } from 'vitest'
import type { PaymentDto } from './api-types'
import { toPayment, toPaymentList, toRecordPaymentResult } from './mapper'

const dto: PaymentDto = {
  id: 1,
  organization_id: 1,
  invoice_id: 1,
  amount_cents: 50000,
  paid_at: '2026-05-30 10:00:00',
  method: 'bank_transfer',
}

describe('toPayment', () => {
  it('maps fields and normalises optional note to null', () => {
    const payment = toPayment(dto)
    expect(payment.id).toBe(1)
    expect(payment.amount_cents).toBe(50000)
    expect(payment.method).toBe('bank_transfer')
    expect(payment.note).toBeNull()
  })
})

describe('toPaymentList', () => {
  it('maps items and carries the running total', () => {
    const list = toPaymentList({ items: [dto], total_paid_cents: 50000 })
    expect(list.items).toHaveLength(1)
    expect(list.total_paid_cents).toBe(50000)
  })
})

describe('toRecordPaymentResult', () => {
  it('extracts payment and total (invoice refresh handled separately)', () => {
    const result = toRecordPaymentResult({
      payment: dto,
      invoice: {
        id: 1,
        organization_id: 1,
        client_id: 5,
        status: 'partially_paid',
        is_overdue: false,
        is_qualified_invoice: true,
        subtotal_cents: 106000,
        tax_cents: 10480,
        total_cents: 116480,
      },
      total_paid_cents: 50000,
    })
    expect(result.payment.id).toBe(1)
    expect(result.total_paid_cents).toBe(50000)
  })
})
