import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { buildInvoiceDto } from '@tests/factories/invoice'
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

  it('exposes pagination meta for a single-page result', async () => {
    const { result } = renderHookWithProviders(() => useListInvoices())

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.pagination.page).toBe(1)
      expect(result.current.pagination.totalPages).toBe(1)
      expect(result.current.pagination.hasPrev).toBe(false)
      expect(result.current.pagination.hasNext).toBe(false)
    }
  })

  it('exposes multi-page pagination and advances to page 2', async () => {
    server.use(
      http.get('/admin/invoices', ({ request }) => {
        const url = new URL(request.url)
        const offset = Number(url.searchParams.get('offset') ?? '0')
        const items = offset === 0 ? [buildInvoiceDto({ id: 1 })] : [buildInvoiceDto({ id: 2 })]
        return HttpResponse.json({ items, total: 25, limit: 20, offset })
      }),
    )

    const { result } = renderHookWithProviders(() => useListInvoices())

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.pagination.totalPages).toBe(2)
      expect(result.current.pagination.hasNext).toBe(true)
      expect(result.current.pagination.hasPrev).toBe(false)

      act(() => {
        result.current.pagination.nextPage()
      })
    }

    await waitFor(() => {
      if (result.current.kind === 'ready') {
        expect(result.current.pagination.page).toBe(2)
        expect(result.current.pagination.hasPrev).toBe(true)
        expect(result.current.pagination.hasNext).toBe(false)
      }
    })
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
