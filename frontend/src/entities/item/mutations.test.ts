import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toItemId } from './ids'
import { useCreateItem, useDeleteItem, useUpdateItem } from './mutations'

describe('useCreateItem', () => {
  it('posts and returns the mapped item', async () => {
    server.use(
      http.post('/admin/items', () =>
        HttpResponse.json(
          {
            id: 9,
            organization_id: 1,
            description: 'Web制作',
            default_unit_price_cents: 300000,
            default_tax_rate_bps: 1000,
          },
          { status: 201 },
        ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateItem())
    result.current.mutate({
      description: 'Web制作',
      default_unit_price_cents: 300000,
      default_tax_rate_bps: 1000,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(9)
    expect(result.current.data?.default_unit_price_cents).toBe(300000)
  })

  it('surfaces an AppError on 422 validation failure', async () => {
    server.use(
      http.post(
        '/admin/items',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/validation-failed',
              title: 'Validation Failed',
              status: 422,
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateItem())
    result.current.mutate({
      description: '',
      default_unit_price_cents: 0,
      default_tax_rate_bps: 1000,
    })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('validation-failed')
  })
})

describe('useDeleteItem', () => {
  it('deletes and resolves with the id', async () => {
    server.use(http.delete('/admin/items/:id', () => new HttpResponse(null, { status: 204 })))

    const { result } = renderHookWithProviders(() => useDeleteItem())
    result.current.mutate(toItemId(5))

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe(5)
  })
})

describe('useUpdateItem', () => {
  it('patches and returns the mapped item', async () => {
    server.use(
      http.patch('/admin/items/:id', () =>
        HttpResponse.json({
          id: 5,
          organization_id: 1,
          description: '更新後',
          default_unit_price_cents: 2500,
          default_tax_rate_bps: 800,
        }),
      ),
    )

    const { result } = renderHookWithProviders(() => useUpdateItem())
    result.current.mutate({
      id: toItemId(5),
      description: '更新後',
      default_unit_price_cents: 2500,
      default_tax_rate_bps: 800,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.description).toBe('更新後')
    expect(result.current.data?.default_tax_rate_bps).toBe(800)
  })
})
