import type { Invoice } from '@/entities/invoice'

export interface DashboardSummary {
  unpaid_count: number
  overdue_count: number
  outstanding_total_cents: number
  recent_unpaid: Invoice[]
}
