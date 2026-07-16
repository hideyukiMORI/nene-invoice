import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListQuotes } from './use-list-quotes'

const quoteDto = (id: number) => ({
  id,
  organization_id: 1,
  client_id: 5,
  client_name: '株式会社サンプル',
  quote_number: `EST-2026-00${String(id)}`,
  status: 'draft',
  subtotal_cents: 1000,
  tax_cents: 100,
  total_cents: 1100,
})

describe('useListQuotes', () => {
  it('exposes the empty state when there are no quotes', async () => {
    const { result } = renderHookWithProviders(() => useListQuotes())
    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })
  })

  it('starts loading then exposes the ready list', async () => {
    server.use(
      http.get('/admin/quotes', () =>
        HttpResponse.json({ items: [quoteDto(1)], total: 1, limit: 20, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListQuotes())
    expect(result.current.state.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    if (result.current.state.kind === 'ready') {
      expect(result.current.state.quotes).toHaveLength(1)
      expect(result.current.state.quotes[0]?.client_name).toBe('株式会社サンプル')
    }
    expect(result.current.pagination.totalPages).toBe(1)
    expect(result.current.pagination.hasNext).toBe(false)
  })

  it('paginates and advances to page 2', async () => {
    server.use(
      http.get('/admin/quotes', ({ request }) => {
        const offset = Number(new URL(request.url).searchParams.get('offset') ?? '0')
        const items = offset === 0 ? [quoteDto(1)] : [quoteDto(2)]
        return HttpResponse.json({ items, total: 25, limit: 20, offset })
      }),
    )

    const { result } = renderHookWithProviders(() => useListQuotes())
    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    expect(result.current.pagination.totalPages).toBe(2)
    expect(result.current.pagination.hasNext).toBe(true)
    act(() => {
      result.current.pagination.nextPage()
    })

    await waitFor(() => {
      expect(result.current.pagination.page).toBe(2)
      expect(result.current.pagination.hasPrev).toBe(true)
    })
  })

  it('sends search / status / sort as query parameters', async () => {
    const seen: string[] = []
    server.use(
      http.get('/admin/quotes', ({ request }) => {
        seen.push(new URL(request.url).search)
        return HttpResponse.json({ items: [], total: 0, limit: 20, offset: 0 })
      }),
    )

    const { result } = renderHookWithProviders(() => useListQuotes())
    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })

    act(() => {
      result.current.applyFilters({
        q: 'EST-001',
        statuses: ['sent'],
        valid_from: '2026-06-01',
        valid_to: '2026-06-30',
        total_min: 1000,
        total_max: 500000,
      })
    })
    act(() => {
      result.current.toggleSort('total')
    })

    await waitFor(() => {
      const last = seen.at(-1) ?? ''
      expect(last).toContain('q=EST-001')
      expect(last).toContain('status=sent')
      expect(last).toContain('valid_from=2026-06-01')
      expect(last).toContain('total_min=1000')
      expect(last).toContain('sort=total')
      expect(last).toContain('order=asc')
    })
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(http.get('/admin/quotes', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListQuotes())
    await waitFor(() => {
      expect(result.current.state.kind).toBe('error')
    })
  })
})
