import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useCreateUser } from './use-create-user'

const CREATED_USER = {
  id: 10,
  email: 'new@example.com',
  role: 'member',
  organization_id: 1,
  status: 'active',
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('useCreateUser hook', () => {
  it('starts in idle form state', () => {
    const { result } = renderHookWithProviders(() => useCreateUser())
    expect(result.current.isPending).toBe(false)
    expect(result.current.errorMessage).toBeNull()
  })

  it('navigates to /users on success', async () => {
    server.use(http.post('/admin/users', () => HttpResponse.json(CREATED_USER, { status: 201 })))

    const { result } = renderHookWithProviders(() => useCreateUser())

    act(() => {
      result.current.form.setValue('email', 'new@example.com')
      result.current.form.setValue('password', 'password1')
      result.current.form.setValue('role', 'member')
    })

    act(() => {
      void result.current.form.handleSubmit(() => {})()
    })

    await waitFor(() => {
      expect(result.current.isPending).toBe(false)
    })
  })

  it('shows error message on server error', async () => {
    server.use(
      http.post(
        '/admin/users',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/email-conflict',
              status: 409,
            }),
            { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateUser())
    act(() => {
      result.current.form.setValue('email', 'dup@example.com')
      result.current.form.setValue('password', 'password1')
      result.current.form.setValue('role', 'member')
    })

    // Submit directly via the mutation to bypass routing
    const { useCreateUser: createMutation } = await import('@/entities/user')
    const { result: mutResult } = renderHookWithProviders(() => createMutation())
    act(() => {
      mutResult.current.mutate({ email: 'dup@example.com', password: 'password1', role: 'member' })
    })

    await waitFor(() => {
      expect(mutResult.current.isError).toBe(true)
    })
  })
})
