import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toClientId } from './ids'
import { useCreateClient, useDeleteClient, useUpdateClient } from './mutations'

describe('useCreateClient', () => {
  it('posts and returns the mapped client', async () => {
    server.use(
      http.post('/admin/clients', () =>
        HttpResponse.json(
          {
            id: 9,
            organization_id: 1,
            name: '新規取引先',
            contact_name: null,
            email: null,
            registration_number: null,
          },
          { status: 201 },
        ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateClient())
    result.current.mutate({
      name: '新規取引先',
      name_kana: null,
      contact_name: null,
      email: null,
      billing_address: null,
      registration_number: null,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(9)
    expect(result.current.data?.name).toBe('新規取引先')
  })

  it('surfaces an AppError on 422 invalid registration number', async () => {
    server.use(
      http.post(
        '/admin/clients',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/invalid-registration-number',
              title: 'Validation Failed',
              status: 422,
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateClient())
    result.current.mutate({
      name: 'X',
      name_kana: null,
      contact_name: null,
      email: null,
      billing_address: null,
      registration_number: 'BAD',
    })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('invalid-registration-number')
  })
})

describe('useDeleteClient', () => {
  it('deletes and resolves with the id', async () => {
    server.use(http.delete('/admin/clients/:id', () => new HttpResponse(null, { status: 204 })))

    const { result } = renderHookWithProviders(() => useDeleteClient())
    result.current.mutate(toClientId(5))

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe(5)
  })
})

describe('useUpdateClient', () => {
  it('patches and returns the mapped client', async () => {
    server.use(
      http.patch('/admin/clients/:id', () =>
        HttpResponse.json({
          id: 5,
          organization_id: 1,
          name: '更新後',
          contact_name: '佐藤',
          email: null,
          billing_address: null,
          registration_number: null,
        }),
      ),
    )

    const { result } = renderHookWithProviders(() => useUpdateClient())
    result.current.mutate({
      id: toClientId(5),
      name: '更新後',
      name_kana: null,
      contact_name: '佐藤',
      email: null,
      billing_address: null,
      registration_number: null,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.name).toBe('更新後')
    expect(result.current.data?.contact_name).toBe('佐藤')
  })
})
