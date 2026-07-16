import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useBankWorkbench } from './use-bank-workbench'

describe('useBankWorkbench', () => {
  it('defaults to the unmatched filter and exposes the ready list', async () => {
    const { result } = renderHookWithProviders(() => useBankWorkbench())

    expect(result.current.status).toBe('unmatched')
    expect(result.current.state.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    if (result.current.state.kind === 'ready') {
      expect(result.current.state.transactions).toHaveLength(1)
      expect(result.current.state.transactions[0]?.direction).toBe('credit')
    }
  })

  it('exposes the empty state when there are no lines', async () => {
    server.use(
      http.get('/admin/bank-transactions', () =>
        HttpResponse.json({ items: [], total: 0, limit: 50, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useBankWorkbench())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(http.get('/admin/bank-transactions', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useBankWorkbench())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('error')
    })
  })

  it('resets to page 1 when the status filter changes', async () => {
    const { result } = renderHookWithProviders(() => useBankWorkbench())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    act(() => {
      result.current.setStatus('posted')
    })
    expect(result.current.status).toBe('posted')
    expect(result.current.pagination.page).toBe(1)
  })
})
