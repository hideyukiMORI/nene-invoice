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
    })
    expect(summary.recent_unpaid).toEqual([])
  })
})
