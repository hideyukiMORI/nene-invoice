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
    }

    const summary = toDashboardSummary(dto)
    expect(summary.unpaid_count).toBe(2)
    expect(summary.overdue_count).toBe(1)
    expect(summary.outstanding_total_cents).toBe(250000)
    expect(summary.recent_unpaid).toHaveLength(2)
    expect(summary.recent_unpaid[0]?.id).toBe(11)
  })

  it('handles an empty recent-unpaid list', () => {
    const summary = toDashboardSummary({
      unpaid_count: 0,
      overdue_count: 0,
      outstanding_total_cents: 0,
      recent_unpaid: [],
    })
    expect(summary.recent_unpaid).toEqual([])
  })
})
