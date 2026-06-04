import type { Invoice } from '@/entities/invoice'

/** Outstanding receivable balance bucketed by overdue age, in cents. */
export interface ReceivableAging {
  current: number
  overdue_1_30: number
  overdue_31_plus: number
}

/** Issued-invoice total for one calendar month (Issue #272). */
export interface MonthlyBilled {
  month: string
  billed_cents: number
  count: number
}

export interface DashboardSummary {
  unpaid_count: number
  overdue_count: number
  outstanding_total_cents: number
  recent_unpaid: Invoice[]
  received_this_month_cents: number
  received_last_month_cents: number
  aging: ReceivableAging
  billed_this_month_cents: number
  billed_last_month_cents: number
  monthly_billed: MonthlyBilled[]
}
