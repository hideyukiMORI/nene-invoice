import { describe, expect, it } from 'vitest'
import { buildInvoiceDto } from '@tests/factories/invoice'
import type { DashboardSummaryDto } from './api-types'
import { toDashboardSummary } from './mapper'

describe('toDashboardSummary', () => {
  it('maps the counts and the recent-unpaid invoice list', () => {
    const dto: DashboardSummaryDto = {
      unpaid_count: 2,
      overdue_count: 1,
      outstanding_total_cents: 250000,
      recent_unpaid: [buildInvoiceDto({ id: 11 }), buildInvoiceDto({ id: 12 })],
      received_this_month_cents: 42000,
      received_last_month_cents: 31000,
      aging: { current: 120000, overdue_1_30: 80000, overdue_31_plus: 50000 },
      billed_this_month_cents: 330000,
      billed_last_month_cents: 210000,
      monthly_billed: [
        { month: '2026-05', billed_cents: 210000, count: 3 },
        { month: '2026-06', billed_cents: 330000, count: 4 },
      ],
      billed_prev_year_month_cents: 180000,
      billed_daily_current: [
        { day: 1, cumulative_cents: 50000 },
        { day: 2, cumulative_cents: 330000 },
      ],
      billed_daily_prev_month: [{ day: 1, cumulative_cents: 210000 }],
    }

    const summary = toDashboardSummary(dto)
    expect(summary.unpaid_count).toBe(2)
    expect(summary.overdue_count).toBe(1)
    expect(summary.outstanding_total_cents).toBe(250000)
    expect(summary.recent_unpaid).toHaveLength(2)
    expect(summary.recent_unpaid[0]?.id).toBe(11)
    expect(summary.received_this_month_cents).toBe(42000)
    expect(summary.received_last_month_cents).toBe(31000)
    expect(summary.aging).toEqual({ current: 120000, overdue_1_30: 80000, overdue_31_plus: 50000 })
    expect(summary.billed_this_month_cents).toBe(330000)
    expect(summary.billed_last_month_cents).toBe(210000)
    expect(summary.monthly_billed).toHaveLength(2)
    expect(summary.monthly_billed[1]).toEqual({ month: '2026-06', billed_cents: 330000, count: 4 })
    expect(summary.billed_prev_year_month_cents).toBe(180000)
    expect(summary.billed_daily_current).toHaveLength(2)
    expect(summary.billed_daily_current[1]).toEqual({ day: 2, cumulative_cents: 330000 })
    expect(summary.billed_daily_prev_month[0]).toEqual({ day: 1, cumulative_cents: 210000 })
  })

  it('handles an empty recent-unpaid list', () => {
    const summary = toDashboardSummary({
      unpaid_count: 0,
      overdue_count: 0,
      outstanding_total_cents: 0,
      recent_unpaid: [],
      received_this_month_cents: 0,
      received_last_month_cents: 0,
      aging: { current: 0, overdue_1_30: 0, overdue_31_plus: 0 },
      billed_this_month_cents: 0,
      billed_last_month_cents: 0,
      monthly_billed: [],
      billed_prev_year_month_cents: 0,
      billed_daily_current: [],
      billed_daily_prev_month: [],
    })
    expect(summary.recent_unpaid).toEqual([])
  })
})
