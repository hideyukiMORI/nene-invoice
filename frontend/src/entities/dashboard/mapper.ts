import { toInvoice } from '@/entities/invoice/mapper'
import type { DashboardSummaryDto } from './api-types'
import type { DashboardSummary } from './model'

export function toDashboardSummary(dto: DashboardSummaryDto): DashboardSummary {
  return {
    unpaid_count: dto.unpaid_count,
    overdue_count: dto.overdue_count,
    outstanding_total_cents: dto.outstanding_total_cents,
    recent_unpaid: dto.recent_unpaid.map(toInvoice),
  }
}
