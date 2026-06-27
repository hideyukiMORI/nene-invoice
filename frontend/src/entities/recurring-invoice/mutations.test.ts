import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { buildRecurringInvoiceWithLinesDto } from '@tests/factories/recurring-invoice'
import { toRecurringInvoiceId } from './ids'
import {
  useCreateRecurringInvoice,
  useDeleteRecurringInvoice,
  useUpdateRecurringInvoice,
} from './mutations'

describe('useCreateRecurringInvoice', () => {
  it('posts and returns the mapped schedule', async () => {
    server.use(
      http.post('/admin/recurring-invoices', () =>
        HttpResponse.json(buildRecurringInvoiceWithLinesDto({ name: '新規スケジュール' }), {
          status: 201,
        }),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateRecurringInvoice())
    result.current.mutate({
      client_id: 5,
      name: '新規スケジュール',
      frequency: 'monthly',
      first_run_on: '2026-07-01',
      line_items: [
        { description: 'Consulting', quantity: 1, unit_price_cents: 100000, tax_rate_bps: 1000 },
      ],
      is_active: true,
      notes: null,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.name).toBe('新規スケジュール')
  })

  it('surfaces an AppError on 422 validation', async () => {
    server.use(
      http.post(
        '/admin/recurring-invoices',
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

    const { result } = renderHookWithProviders(() => useCreateRecurringInvoice())
    result.current.mutate({
      client_id: 5,
      name: '',
      frequency: 'monthly',
      first_run_on: '2026-07-01',
      line_items: [{ description: 'X', quantity: 1, unit_price_cents: 0, tax_rate_bps: 1000 }],
      is_active: true,
      notes: null,
    })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('validation-failed')
  })
})

describe('useUpdateRecurringInvoice', () => {
  it('patches and returns the mapped schedule', async () => {
    server.use(
      http.patch('/admin/recurring-invoices/:id', () =>
        HttpResponse.json(
          buildRecurringInvoiceWithLinesDto({ name: '改定 顧問料', frequency: 'quarterly' }),
        ),
      ),
    )

    const { result } = renderHookWithProviders(() => useUpdateRecurringInvoice())
    result.current.mutate({
      id: toRecurringInvoiceId(7),
      client_id: 5,
      name: '改定 顧問料',
      frequency: 'quarterly',
      next_run_on: '2026-09-01',
      line_items: [
        { description: 'Consulting', quantity: 2, unit_price_cents: 100000, tax_rate_bps: 1000 },
      ],
      is_active: true,
      notes: null,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.name).toBe('改定 顧問料')
    expect(result.current.data?.frequency).toBe('quarterly')
  })
})

describe('useDeleteRecurringInvoice', () => {
  it('deletes and resolves with the id', async () => {
    server.use(
      http.delete('/admin/recurring-invoices/:id', () => new HttpResponse(null, { status: 204 })),
    )

    const { result } = renderHookWithProviders(() => useDeleteRecurringInvoice())
    result.current.mutate(toRecurringInvoiceId(7))

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data).toBe(7)
  })
})
