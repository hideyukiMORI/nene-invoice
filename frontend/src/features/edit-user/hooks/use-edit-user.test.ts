import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toUserId } from '@/entities/user'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useEditUser } from './use-edit-user'

const USER_DTO = {
  id: 7,
  email: 'admin@example.com',
  role: 'admin',
  organization_id: 1,
  status: 'active',
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('useEditUser', () => {
  it('returns loading then prefills form with user data', async () => {
    server.use(
      http.get('/admin/users/:id', () => HttpResponse.json(USER_DTO)),
    )

    const { result } = renderHookWithProviders(() => useEditUser(toUserId(7)))

    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.form.getValues('email')).toBe('admin@example.com')
      expect(result.current.form.getValues('role')).toBe('admin')
      // password is blank (unchanged)
      expect(result.current.form.getValues('password')).toBe('')
    }
  })

  it('returns error state when user is not found', async () => {
    server.use(http.get('/admin/users/:id', () => new HttpResponse(null, { status: 404 })))

    const { result } = renderHookWithProviders(() => useEditUser(toUserId(999)))

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })

  it('downgrades superadmin role to admin in the form', async () => {
    server.use(
      http.get('/admin/users/:id', () =>
        HttpResponse.json({ ...USER_DTO, role: 'superadmin' }),
      ),
    )

    const { result } = renderHookWithProviders(() => useEditUser(toUserId(7)))

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.form.getValues('role')).toBe('admin')
    }
  })
})
