import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { hasAuthToken, setAuthToken } from '@/shared/api/client'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { signOut, useLogin } from './mutations'

describe('useLogin', () => {
  it('stores the bearer token on success', async () => {
    setAuthToken(null)
    const { result } = renderHookWithProviders(() => useLogin())
    result.current.mutate({ email: 'admin@example.com', password: 'correct-horse' })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe('test-token')
    expect(hasAuthToken()).toBe(true)
  })

  it('surfaces an AppError and leaves the session unauthenticated on 401', async () => {
    setAuthToken(null)
    server.use(
      http.post(
        '/auth/login',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/invalid-credentials',
              title: 'Unauthorized',
              status: 401,
            }),
            { status: 401, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useLogin())
    result.current.mutate({ email: 'nobody@example.com', password: 'wrong' })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('invalid-credentials')
    expect(hasAuthToken()).toBe(false)
  })
})

describe('signOut', () => {
  it('clears the in-memory token and revokes the session server-side', async () => {
    let loggedOut = false
    server.use(
      http.post('/auth/logout', () => {
        loggedOut = true
        return new HttpResponse(null, { status: 204 })
      }),
    )
    setAuthToken('active-token')

    signOut()

    // Local token is cleared immediately (fail-closed, no wait on the network) …
    expect(hasAuthToken()).toBe(false)
    // … and the server-side revocation is fired best-effort.
    await waitFor(() => {
      expect(loggedOut).toBe(true)
    })
  })
})
