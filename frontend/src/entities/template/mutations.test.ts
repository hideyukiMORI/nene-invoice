import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toTemplateId } from './ids'
import { useCreateTemplate, useDeleteTemplate, useUpdateTemplate } from './mutations'

describe('useCreateTemplate', () => {
  it('posts and returns the mapped template with lines', async () => {
    server.use(
      http.post('/admin/templates', () =>
        HttpResponse.json(
          {
            id: 9,
            organization_id: 1,
            name: '新テンプレ',
            notes: null,
            line_items: [
              {
                id: 1,
                description: '作業',
                quantity: 2,
                unit_price_cents: 1000,
                tax_rate_bps: 1000,
              },
            ],
          },
          { status: 201 },
        ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateTemplate())
    result.current.mutate({
      name: '新テンプレ',
      notes: null,
      line_items: [
        { description: '作業', quantity: 2, unit_price_cents: 1000, tax_rate_bps: 1000 },
      ],
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(9)
    expect(result.current.data?.line_items).toHaveLength(1)
  })

  it('surfaces an AppError on 422', async () => {
    server.use(
      http.post(
        '/admin/templates',
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

    const { result } = renderHookWithProviders(() => useCreateTemplate())
    result.current.mutate({ name: '', notes: null, line_items: [] })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('validation-failed')
  })
})

describe('useUpdateTemplate', () => {
  it('patches and returns the mapped template', async () => {
    server.use(
      http.patch('/admin/templates/:id', () =>
        HttpResponse.json({
          id: 5,
          organization_id: 1,
          name: '更新後',
          notes: 'メモ',
          line_items: [],
        }),
      ),
    )

    const { result } = renderHookWithProviders(() => useUpdateTemplate())
    result.current.mutate({ id: toTemplateId(5), name: '更新後', notes: 'メモ', line_items: [] })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.name).toBe('更新後')
  })
})

describe('useDeleteTemplate', () => {
  it('deletes and resolves with the id', async () => {
    server.use(http.delete('/admin/templates/:id', () => new HttpResponse(null, { status: 204 })))

    const { result } = renderHookWithProviders(() => useDeleteTemplate())
    result.current.mutate(toTemplateId(5))

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe(5)
  })
})
