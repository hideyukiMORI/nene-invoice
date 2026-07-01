import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toOrganizationId } from './ids'
import { useCreateOrganization, useDeleteOrganization } from './mutations'

const ORG_DTO = {
  id: 5,
  name: 'Beta KK',
  slug: 'beta',
  plan: 'free',
  is_active: true,
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('useCreateOrganization', () => {
  it('posts org-only and returns the mapped organization', async () => {
    let sentBody: unknown = null
    server.use(
      http.post('/admin/organizations', async ({ request }) => {
        sentBody = await request.json()
        return HttpResponse.json(ORG_DTO, { status: 201 })
      }),
    )

    const { result } = renderHookWithProviders(() => useCreateOrganization())
    act(() => {
      result.current.mutate({ name: 'Beta KK', slug: 'beta' })
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(5)
    expect(sentBody).toEqual({ name: 'Beta KK', slug: 'beta' })
  })

  it('includes admin credentials when both are present', async () => {
    let sentBody: Record<string, unknown> = {}
    server.use(
      http.post('/admin/organizations', async ({ request }) => {
        sentBody = (await request.json()) as Record<string, unknown>
        return HttpResponse.json(ORG_DTO, { status: 201 })
      }),
    )

    const { result } = renderHookWithProviders(() => useCreateOrganization())
    act(() => {
      result.current.mutate({
        name: 'Beta KK',
        slug: 'beta',
        plan: 'pro',
        adminEmail: 'owner@beta.example',
        adminPassword: 'correct horse battery',
      })
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(sentBody['plan']).toBe('pro')
    expect(sentBody['admin_email']).toBe('owner@beta.example')
    expect(sentBody['admin_password']).toBe('correct horse battery')
  })

  it('surfaces an error on 409 slug conflict', async () => {
    server.use(
      http.post(
        '/admin/organizations',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/organization-slug-conflict',
              title: 'Conflict',
              status: 409,
            }),
            { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateOrganization())
    act(() => {
      result.current.mutate({ name: 'Dup', slug: 'dup' })
    })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
  })
})

describe('useDeleteOrganization', () => {
  it('deletes and returns the id', async () => {
    server.use(
      http.delete('/admin/organizations/:id', () => new HttpResponse(null, { status: 204 })),
    )

    const { result } = renderHookWithProviders(() => useDeleteOrganization())
    act(() => {
      result.current.mutate(toOrganizationId(5))
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe(5)
  })
})
