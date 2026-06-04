import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { buildInvoiceDto } from '@tests/factories/invoice'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useViewDashboard } from './use-view-dashboard'

describe('useViewDashboard', () => {
  it('starts loading then exposes the ready summary', async () => {
    const { result } = renderHookWithProviders(() => useViewDashboard())
    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.unpaidCount).toBe(0)
      expect(result.current.recentUnpaid).toEqual([])
    }
  })

  it('maps counts and the recent-unpaid list', async () => {
    server.use(
      http.get('/admin/dashboard', () =>
        HttpResponse.json({
          unpaid_count: 3,
          overdue_count: 1,
          outstanding_total_cents: 250000,
          recent_unpaid: [buildInvoiceDto({ id: 11 })],
          received_this_month_cents: 0,
          received_last_month_cents: 0,
          aging: { current: 0, overdue_1_30: 0, overdue_31_plus: 0 },
          billed_this_month_cents: 0,
          billed_last_month_cents: 0,
          monthly_billed: [],
        }),
      ),
    )

    const { result } = renderHookWithProviders(() => useViewDashboard())
    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.unpaidCount).toBe(3)
      expect(result.current.overdueCount).toBe(1)
      expect(result.current.outstandingTotalCents).toBe(250000)
      expect(result.current.recentUnpaid[0]?.id).toBe(11)
    }
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(http.get('/admin/dashboard', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useViewDashboard())
    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
