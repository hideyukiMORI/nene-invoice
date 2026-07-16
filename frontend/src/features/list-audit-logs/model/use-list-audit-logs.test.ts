import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useListAuditLogs } from './use-list-audit-logs'

const LOG_DTO = {
  id: 12,
  actor_user_id: 7,
  organization_id: 1,
  action: 'invoice.issued',
  entity_type: 'invoice',
  entity_id: 42,
  before: { status: 'draft' },
  after: { status: 'issued' },
  created_at: '2026-05-01 09:00:00',
}

describe('useListAuditLogs', () => {
  it('returns ready state with logs', async () => {
    server.use(
      http.get('/admin/audit-logs', () =>
        HttpResponse.json({ items: [LOG_DTO], total: 1, limit: 20, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListAuditLogs())

    expect(result.current.state.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.state.kind).toBe('ready')
    })

    if (result.current.state.kind === 'ready') {
      expect(result.current.state.logs).toHaveLength(1)
      expect(result.current.state.logs[0]?.action).toBe('invoice.issued')
    }
  })

  it('returns empty state when no logs match', async () => {
    server.use(
      http.get('/admin/audit-logs', () =>
        HttpResponse.json({ items: [], total: 0, limit: 20, offset: 0 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useListAuditLogs())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })
  })

  it('returns error state on 5xx', async () => {
    server.use(http.get('/admin/audit-logs', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useListAuditLogs())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('error')
    })
  })

  it('sends applied filters as query parameters and resets offset', async () => {
    const seen: string[] = []
    server.use(
      http.get('/admin/audit-logs', ({ request }) => {
        seen.push(new URL(request.url).search)
        return HttpResponse.json({ items: [], total: 0, limit: 20, offset: 0 })
      }),
    )

    const { result } = renderHookWithProviders(() => useListAuditLogs())

    await waitFor(() => {
      expect(result.current.state.kind).toBe('empty')
    })

    act(() => {
      result.current.applyFilters({
        entity_type: 'invoice',
        action: 'invoice.issued',
        actor_user_id: 7,
        created_from: '2026-05-01',
        created_to: '2026-05-31',
      })
    })

    await waitFor(() => {
      const last = seen.at(-1) ?? ''
      expect(last).toContain('entity_type=invoice')
      expect(last).toContain('action=invoice.issued')
      expect(last).toContain('actor_user_id=7')
      expect(last).toContain('created_from=2026-05-01')
      expect(last).toContain('created_to=2026-05-31')
      expect(last).toContain('offset=0')
    })
  })
})
