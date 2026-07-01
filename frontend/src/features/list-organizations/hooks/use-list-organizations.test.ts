import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListOrganizations } from './use-list-organizations'

const ORG_DTO = {
  id: 3,
  name: 'Acme KK',
  slug: 'acme',
  plan: 'free',
  is_active: true,
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('useListOrganizations', () => {
  it('returns ready state with organizations', async () => {
    server.use(
      http.get('/admin/organizations', () =>
        HttpResponse.json({ items: [ORG_DTO], total: 1, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListOrganizations())

    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.organizations).toHaveLength(1)
      expect(result.current.organizations[0]?.name).toBe('Acme KK')
    }
  })

  it('returns empty state when no organizations', async () => {
    server.use(
      http.get('/admin/organizations', () =>
        HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListOrganizations())

    await waitFor(() => {
      expect(result.current.kind).toBe('empty')
    })
  })

  it('returns error state on 5xx', async () => {
    server.use(http.get('/admin/organizations', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListOrganizations())

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
