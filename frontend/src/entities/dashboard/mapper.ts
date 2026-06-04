import { toInvoice } from '@/entities/invoice/mapper'
import type { DashboardSummaryDto } from './api-types'
import type { DashboardSummary } from './model'

export function toDashboardSummary(dto: DashboardSummaryDto): DashboardSummary {
  return {
    unpaid_count: dto.unpaid_count,
    overdue_count: dto.overdue_count,
    outstanding_total_cents: dto.outstanding_total_cents,
    recent_unpaid: dto.recent_unpaid.map(toInvoice),
    received_this_month_cents: dto.received_this_month_cents,
    received_last_month_cents: dto.received_last_month_cents,
    aging: {
      current: dto.aging.current,
      overdue_1_30: dto.aging.overdue_1_30,
      overdue_31_plus: dto.aging.overdue_31_plus,
    },
    billed_this_month_cents: dto.billed_this_month_cents,
    billed_last_month_cents: dto.billed_last_month_cents,
    monthly_billed: dto.monthly_billed.map((m) => ({
      month: m.month,
      billed_cents: m.billed_cents,
      count: m.count,
    })),
    billed_prev_year_month_cents: dto.billed_prev_year_month_cents,
    billed_daily_current: dto.billed_daily_current.map((d) => ({
      day: d.day,
      cumulative_cents: d.cumulative_cents,
    })),
    billed_daily_prev_month: dto.billed_daily_prev_month.map((d) => ({
      day: d.day,
      cumulative_cents: d.cumulative_cents,
    })),
  }
}
