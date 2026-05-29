import { waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { hasAuthToken } from '@/shared/api/client'
import { server } from '@tests/msw/server'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { SignInForm } from './SignInForm'

// jsdom navigator.language defaults to en-US, so the form renders the en catalog.
describe('SignInForm', () => {
  it('signs in and stores the session token', async () => {
    const user = userEvent.setup()
    const { getByLabelText, getByRole } = renderWithProviders(<SignInForm />)

    await user.type(getByLabelText('Email'), 'admin@example.com')
    await user.type(getByLabelText('Password'), 'password123')
    await user.click(getByRole('button', { name: 'Sign in' }))

    await waitFor(() => {
      expect(hasAuthToken()).toBe(true)
    })
  })

  it('shows an error and does not start a session on bad credentials', async () => {
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

    const user = userEvent.setup()
    const { getByLabelText, getByRole, findByRole } = renderWithProviders(<SignInForm />)

    await user.type(getByLabelText('Email'), 'admin@example.com')
    await user.type(getByLabelText('Password'), 'wrong-password')
    await user.click(getByRole('button', { name: 'Sign in' }))

    await findByRole('alert')
    expect(hasAuthToken()).toBe(false)
  })
})
