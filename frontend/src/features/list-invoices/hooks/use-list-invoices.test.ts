import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListInvoices } from './use-list-invoices'

describe('useListInvoices', () => {
  it('starts loading then exposes the ready list', async () => {
    const { result } = renderHookWithProviders(() => useListInvoices())

    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.invoices).toHaveLength(1)
      expect(result.current.invoices[0]?.total_cents).toBe(116480)
    }
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(http.get('/admin/invoices', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListInvoices())

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })

  it('exposes the empty state when there are no invoices', async () => {
    server.use(
      http.get('/admin/invoices', () =>
        HttpResponse.json({ items: [], total: 0, limit: 20, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListInvoices())

    await waitFor(() => {
      expect(result.current.kind).toBe('empty')
    })
  })
})
