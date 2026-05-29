export type PaymentMethod = 'bank_transfer' | 'cash' | 'other'

export interface Payment {
  id: number
  amount_cents: number
  paid_at: string
  method: PaymentMethod | null
  note: string | null
}

export interface PaymentList {
  items: Payment[]
  total_paid_cents: number
}

export interface RecordPaymentInput {
  invoice_id: number
  amount_cents: number
  method: PaymentMethod | null
  note: string | null
}

export interface RecordPaymentResult {
  payment: Payment
  total_paid_cents: number
}
