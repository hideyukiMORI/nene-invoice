import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toServiceTokenId } from './ids'
import { useIssueServiceToken, useRevokeServiceToken } from './mutations'

const CREATED_DTO = {
  id: 5,
  subject: 'service:clear',
  label: 'NeNe Clear',
  scopes: ['read:invoices'],
  created_by: 7,
  created_at: '2026-06-09 00:00:00',
  expires_at: '2026-07-09 00:00:00',
  revoked_at: null,
  status: 'active',
  token: 'signed.jwt.value',
}

describe('useIssueServiceToken', () => {
  it('posts and returns the issued token with its one-time value', async () => {
    server.use(
      http.post('/admin/service-tokens', () => HttpResponse.json(CREATED_DTO, { status: 201 })),
    )

    const { result } = renderHookWithProviders(() => useIssueServiceToken())
    act(() => {
      result.current.mutate({ label: 'NeNe Clear', scopes: ['read:invoices'] })
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(5)
    expect(result.current.data?.token).toBe('signed.jwt.value')
  })

  it('surfaces a validation error', async () => {
    server.use(
      http.post(
        '/admin/service-tokens',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/validation-failed',
              title: 'Validation failed',
              status: 422,
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useIssueServiceToken())
    act(() => {
      result.current.mutate({ label: '', scopes: [] })
    })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
  })
})

describe('useRevokeServiceToken', () => {
  it('deletes by id and resolves with the id', async () => {
    server.use(
      http.delete('/admin/service-tokens/:id', () => new HttpResponse(null, { status: 204 })),
    )

    const { result } = renderHookWithProviders(() => useRevokeServiceToken())
    act(() => {
      result.current.mutate(toServiceTokenId(5))
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe(5)
  })
})
