import { http, HttpResponse } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { apiClient, hasAuthToken, setAuthToken, wasSessionExpired } from './client'

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
