import type { PaymentDto, PaymentListDto, RecordPaymentResponseDto } from './api-types'
import type { Payment, PaymentList, RecordPaymentResult } from './model'

export function toPayment(dto: PaymentDto): Payment {
  return {
    id: dto.id,
    amount_cents: dto.amount_cents,
    paid_at: dto.paid_at,
    method: dto.method ?? null,
    note: dto.note ?? null,
  }
}

export function toPaymentList(dto: PaymentListDto): PaymentList {
  return {
    items: dto.items.map(toPayment),
    total_paid_cents: dto.total_paid_cents,
  }
}

export function toRecordPaymentResult(dto: RecordPaymentResponseDto): RecordPaymentResult {
  return {
    payment: toPayment(dto.payment),
    total_paid_cents: dto.total_paid_cents,
  }
}
