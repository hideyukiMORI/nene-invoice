import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListClients } from './use-list-clients'

describe('useListClients', () => {
  it('loads then exposes the ready list', async () => {
    const { result } = renderHookWithProviders(() => useListClients())

    expect(result.current.state.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    if (result.current.state.kind === 'ready') {
      expect(result.current.state.clients).toHaveLength(1)
      expect(result.current.state.clients[0]?.name).toBe('得意先ABC')
    }
  })

  it('sends search and sort as query parameters', async () => {
    const seen: string[] = []
    server.use(
      http.get('/admin/clients', ({ request }) => {
        seen.push(new URL(request.url).search)
        return HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 })
      }),
    )

    const { result } = renderHookWithProviders(() => useListClients())
    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })

    act(() => {
      result.current.applyFilters({ q: 'アルファ' })
    })
    act(() => {
      result.current.toggleSort('name')
    })

    await waitFor(() => {
      const last = seen.at(-1) ?? ''
      expect(last).toContain('q=')
      expect(last).toContain('sort=name')
      expect(last).toContain('order=asc')
    })
  })

  it('exposes the empty state when there are no clients', async () => {
    server.use(
      http.get('/admin/clients', () =>
        HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListClients())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(http.get('/admin/clients', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListClients())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('error')
    })
  })
})
