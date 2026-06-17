import { screen, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { setAuthToken } from '@/shared/api/client'
import { server } from '@tests/msw/server'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { AuthGate } from './auth-gate'

describe('AuthGate (ADR 0014 silent refresh)', () => {
  beforeEach(() => {
    setAuthToken('seed')
    setAuthToken(null)
  })

  it('restores the session on mount and reveals the app (no login flash)', async () => {
    // Default handler: /auth/refresh succeeds → token seated.
    renderWithProviders(
      <AuthGate>
        <div>PROTECTED CONTENT</div>
      </AuthGate>,
    )

    expect(await screen.findByText('PROTECTED CONTENT')).toBeInTheDocument()
  })

  it('falls through to the login screen when the refresh fails', async () => {
    server.use(http.post('/auth/refresh', () => new HttpResponse(null, { status: 401 })))

    renderWithProviders(
      <AuthGate>
        <div>PROTECTED CONTENT</div>
      </AuthGate>,
    )

    // The probe resolves to a signed-out state → login, never the protected app.
    await waitFor(() => {
      expect(screen.queryByText('PROTECTED CONTENT')).not.toBeInTheDocument()
    })
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument()
  })
})
