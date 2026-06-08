import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListServiceTokens } from './use-list-service-tokens'

const TOKEN_DTO = {
  id: 5,
  subject: 'service:clear',
  label: 'NeNe Clear',
  scopes: ['read:invoices'],
  created_by: 7,
  created_at: '2026-06-09 00:00:00',
  expires_at: '2026-07-09 00:00:00',
  revoked_at: null,
  status: 'active',
}

describe('useListServiceTokens', () => {
  it('returns ready state with tokens', async () => {
    server.use(
      http.get('/admin/service-tokens', () =>
        HttpResponse.json({ items: [TOKEN_DTO], total: 1, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListServiceTokens())
    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.tokens).toHaveLength(1)
      expect(result.current.tokens[0]?.label).toBe('NeNe Clear')
    }
  })

  it('returns empty state when none issued', async () => {
    server.use(
      http.get('/admin/service-tokens', () =>
        HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListServiceTokens())

    await waitFor(() => {
      expect(result.current.kind).toBe('empty')
    })
  })

  it('returns error state on 5xx', async () => {
    server.use(http.get('/admin/service-tokens', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListServiceTokens())

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
