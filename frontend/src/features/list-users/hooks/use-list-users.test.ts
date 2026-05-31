import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListUsers } from './use-list-users'

const USER_DTO = {
  id: 7,
  email: 'admin@example.com',
  role: 'admin',
  organization_id: 1,
  status: 'active',
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('useListUsers', () => {
  it('returns ready state with users', async () => {
    server.use(
      http.get('/admin/users', () =>
        HttpResponse.json({ items: [USER_DTO], total: 1, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListUsers())

    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.users).toHaveLength(1)
      expect(result.current.users[0]?.email).toBe('admin@example.com')
    }
  })

  it('returns empty state when no users', async () => {
    server.use(
      http.get('/admin/users', () =>
        HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListUsers())

    await waitFor(() => {
      expect(result.current.kind).toBe('empty')
    })
  })

  it('returns error state on 5xx', async () => {
    server.use(http.get('/admin/users', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListUsers())

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
