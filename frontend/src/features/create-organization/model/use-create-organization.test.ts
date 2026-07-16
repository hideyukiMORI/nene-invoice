import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import type { SyntheticEvent } from 'react'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useCreateOrganization } from './use-create-organization'

const CREATED_ORG = {
  id: 5,
  name: 'Beta KK',
  slug: 'beta',
  plan: 'free',
  is_active: true,
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('useCreateOrganization hook', () => {
  it('starts in idle form state without the admin fields', () => {
    const { result } = renderHookWithProviders(() => useCreateOrganization())
    expect(result.current.isPending).toBe(false)
    expect(result.current.errorMessage).toBeNull()
    expect(result.current.createAdmin).toBe(false)
  })

  it('submits org-only when the admin toggle is off', async () => {
    let sentBody: Record<string, unknown> = {}
    server.use(
      http.post('/admin/organizations', async ({ request }) => {
        sentBody = (await request.json()) as Record<string, unknown>
        return HttpResponse.json(CREATED_ORG, { status: 201 })
      }),
    )

    const { result } = renderHookWithProviders(() => useCreateOrganization())
    act(() => {
      result.current.form.setValue('name', 'Beta KK')
      result.current.form.setValue('slug', 'beta')
    })
    act(() => {
      result.current.onSubmit({ preventDefault: () => {} } as SyntheticEvent)
    })

    await waitFor(() => {
      expect(sentBody['name']).toBe('Beta KK')
    })
    expect(sentBody['slug']).toBe('beta')
    expect(sentBody).not.toHaveProperty('admin_email')
  })

  it('reveals the admin fields when the toggle is on', () => {
    const { result } = renderHookWithProviders(() => useCreateOrganization())
    act(() => {
      result.current.form.setValue('createAdmin', true)
    })
    expect(result.current.createAdmin).toBe(true)
  })
})
