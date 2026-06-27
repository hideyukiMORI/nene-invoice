import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListRecurringInvoices } from './use-list-recurring-invoices'

describe('useListRecurringInvoices', () => {
  it('loads then exposes the ready list', async () => {
    const { result } = renderHookWithProviders(() => useListRecurringInvoices())

    expect(result.current.state.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    if (result.current.state.kind === 'ready') {
      expect(result.current.state.recurringInvoices).toHaveLength(1)
      expect(result.current.state.recurringInvoices[0]?.name).toBe('月次顧問料')
    }
  })

  it('exposes the empty state when there are no schedules', async () => {
    server.use(
      http.get('/admin/recurring-invoices', () =>
        HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListRecurringInvoices())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(http.get('/admin/recurring-invoices', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListRecurringInvoices())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('error')
    })
  })
})
