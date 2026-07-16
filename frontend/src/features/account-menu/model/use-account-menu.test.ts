import { act, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { hasAuthToken, setAuthToken } from '@/shared/api/client'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useAccountMenu } from './use-account-menu'

const navigate = vi.fn()
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>()
  return { ...actual, useNavigate: () => navigate }
})

describe('useAccountMenu', () => {
  it('exposes the current user email and role and signs out', async () => {
    setAuthToken('test-token')
    const { result } = renderHookWithProviders(() => useAccountMenu())

    await waitFor(() => {
      expect(result.current.email).toBe('admin@example.com')
    })
    expect(result.current.role).toBe('admin')

    act(() => {
      result.current.onSignOut()
    })

    expect(hasAuthToken()).toBe(false)
    // #654: sign-out cleans the URL back to the root so the deep admin path is
    // not left in the address bar behind the login screen.
    expect(navigate).toHaveBeenCalledWith('/', { replace: true })
  })
})
