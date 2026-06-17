import { http, HttpResponse } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import {
  apiClient,
  hasAuthToken,
  refreshSession,
  revokeSession,
  setAuthToken,
  wasSessionExpired,
} from './client'

describe('apiClient — 401 session handling', () => {
  beforeEach(() => {
    // Clean slate: a non-null set clears the expiry flag, then null clears the token.
    setAuthToken('seed')
    setAuthToken(null)
  })

  it('clears the token and flags expiry on a 401 while signed in', async () => {
    setAuthToken('valid-token')
    server.use(http.get('/admin/users', () => new HttpResponse(null, { status: 401 })))

    await expect(apiClient.get('/admin/users')).rejects.toBeDefined()

    expect(hasAuthToken()).toBe(false)
    expect(wasSessionExpired()).toBe(true)
  })

  it('does not flag expiry for a 401 when not signed in (failed login)', async () => {
    server.use(http.post('/auth/login', () => new HttpResponse(null, { status: 401 })))

    await expect(
      apiClient.post('/auth/login', { email: 'a@b.c', password: 'x' }),
    ).rejects.toBeDefined()

    expect(wasSessionExpired()).toBe(false)
  })

  it('clears the expiry flag on the next successful sign-in', async () => {
    setAuthToken('valid-token')
    server.use(http.get('/admin/clients', () => new HttpResponse(null, { status: 401 })))
    await expect(apiClient.get('/admin/clients')).rejects.toBeDefined()
    expect(wasSessionExpired()).toBe(true)

    setAuthToken('new-token')
    expect(wasSessionExpired()).toBe(false)
  })

  it('also clears the session when a blob/CSV download 401s', async () => {
    setAuthToken('valid-token')
    server.use(http.get('/admin/clients/export', () => new HttpResponse(null, { status: 401 })))

    await expect(apiClient.getBlob('/admin/clients/export')).rejects.toBeDefined()

    expect(hasAuthToken()).toBe(false)
    expect(wasSessionExpired()).toBe(true)
  })
})

describe('silent re-authentication (ADR 0014)', () => {
  beforeEach(() => {
    setAuthToken('seed')
    setAuthToken(null)
  })

  it('transparently refreshes and replays a mid-session 401', async () => {
    setAuthToken('expired-token')
    let calls = 0
    server.use(
      http.get('/admin/users', () => {
        calls += 1
        // Expired access token on the first hit; succeeds after the silent refresh.
        return calls === 1
          ? new HttpResponse(null, { status: 401 })
          : HttpResponse.json({ items: [], total: 0, limit: 100, offset: 0 })
      }),
    )

    const data = await apiClient.get('/admin/users')

    expect(data).toEqual({ items: [], total: 0, limit: 100, offset: 0 })
    expect(calls).toBe(2)
    // The session survived transparently: token rotated, no expiry notice.
    expect(hasAuthToken()).toBe(true)
    expect(wasSessionExpired()).toBe(false)
  })

  it('fails closed when the refresh cookie is also dead', async () => {
    setAuthToken('valid-token')
    server.use(
      http.get('/admin/users', () => new HttpResponse(null, { status: 401 })),
      http.post('/auth/refresh', () => new HttpResponse(null, { status: 401 })),
    )

    await expect(apiClient.get('/admin/users')).rejects.toBeDefined()

    expect(hasAuthToken()).toBe(false)
    expect(wasSessionExpired()).toBe(true)
  })

  it('restores a session from the refresh cookie on app start', async () => {
    const restored = await refreshSession()

    expect(restored).toBe(true)
    expect(hasAuthToken()).toBe(true)
  })

  it('stays signed out when the app-start refresh fails', async () => {
    server.use(http.post('/auth/refresh', () => new HttpResponse(null, { status: 401 })))

    const restored = await refreshSession()

    expect(restored).toBe(false)
    expect(hasAuthToken()).toBe(false)
  })

  it('de-duplicates concurrent refreshes into one request (single-flight)', async () => {
    let refreshCalls = 0
    server.use(
      http.post('/auth/refresh', () => {
        refreshCalls += 1
        return HttpResponse.json({ token: 'refreshed-token' })
      }),
    )

    await Promise.all([refreshSession(), refreshSession(), refreshSession()])

    expect(refreshCalls).toBe(1)
    expect(hasAuthToken()).toBe(true)
  })

  it('revokeSession posts to the logout endpoint', async () => {
    let loggedOut = false
    server.use(
      http.post('/auth/logout', () => {
        loggedOut = true
        return new HttpResponse(null, { status: 204 })
      }),
    )

    await revokeSession()

    expect(loggedOut).toBe(true)
  })
})
