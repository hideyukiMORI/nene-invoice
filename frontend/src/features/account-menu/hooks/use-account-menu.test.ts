import { act, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { hasAuthToken, setAuthToken } from '@/shared/api/client'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useAccountMenu } from './use-account-menu'

describe('useAccountMenu', () => {
  it('exposes the current user email and signs out', async () => {
    setAuthToken('test-token')
    const { result } = renderHookWithProviders(() => useAccountMenu())

    await waitFor(() => {
      expect(result.current.email).toBe('admin@example.com')
    })

    act(() => {
      result.current.onSignOut()
    })

    expect(hasAuthToken()).toBe(false)
  })
})
