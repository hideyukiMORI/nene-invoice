import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListItems } from './use-list-items'

describe('useListItems', () => {
  it('loads then exposes the ready list', async () => {
    const { result } = renderHookWithProviders(() => useListItems())

    expect(result.current.state.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    if (result.current.state.kind === 'ready') {
      expect(result.current.state.items).toHaveLength(1)
      expect(result.current.state.items[0]?.description).toBe('保守サポート（月額）')
    }
  })

  it('sends search and sort as query parameters', async () => {
    const seen: string[] = []
    server.use(
      http.get('/admin/items', ({ request }) => {
        seen.push(new URL(request.url).search)
        return HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 })
      }),
    )

    const { result } = renderHookWithProviders(() => useListItems())
    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })

    act(() => {
      result.current.applyFilters({ q: '保守' })
    })
    act(() => {
      result.current.toggleSort('unit_price')
    })

    await waitFor(() => {
      const last = seen.at(-1) ?? ''
      expect(last).toContain('q=')
      expect(last).toContain('sort=unit_price')
      expect(last).toContain('order=asc')
    })
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(http.get('/admin/items', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListItems())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('error')
    })
  })
})
