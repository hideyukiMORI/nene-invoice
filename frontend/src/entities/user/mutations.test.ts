import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toUserId } from './ids'
import { useCreateUser, useDeleteUser, useUpdateUser } from './mutations'

const USER_DTO = {
  id: 7,
  email: 'new@example.com',
  role: 'member',
  organization_id: 1,
  status: 'active',
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('useCreateUser', () => {
  it('posts and returns the mapped user', async () => {
    server.use(
      http.post('/admin/users', () => HttpResponse.json(USER_DTO, { status: 201 })),
    )

    const { result } = renderHookWithProviders(() => useCreateUser())
    act(() => {
      result.current.mutate({ email: 'new@example.com', password: 'password1', role: 'member' })
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(7)
    expect(result.current.data?.email).toBe('new@example.com')
    expect(result.current.data?.role).toBe('member')
  })

  it('surfaces an error on 409 email conflict', async () => {
    server.use(
      http.post(
        '/admin/users',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/email-conflict',
              title: 'Email conflict',
              status: 409,
            }),
            { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateUser())
    act(() => {
      result.current.mutate({ email: 'dup@example.com', password: 'password1', role: 'member' })
    })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
  })
})

describe('useUpdateUser', () => {
  it('patches and returns the updated user', async () => {
    server.use(
      http.patch('/admin/users/:id', () => HttpResponse.json({ ...USER_DTO, role: 'admin' })),
    )

    const { result } = renderHookWithProviders(() => useUpdateUser())
    act(() => {
      result.current.mutate({ id: toUserId(7), role: 'admin' })
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.role).toBe('admin')
  })
})

describe('useDeleteUser', () => {
  it('deletes and returns the id', async () => {
    server.use(
      http.delete('/admin/users/:id', () => new HttpResponse(null, { status: 204 })),
    )

    const { result } = renderHookWithProviders(() => useDeleteUser())
    act(() => {
      result.current.mutate(toUserId(7))
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe(7)
  })
})
