import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useCreateInvoice } from './mutations'

describe('useCreateInvoice', () => {
  it('posts and returns the mapped invoice on success', async () => {
    const { result } = renderHookWithProviders(() => useCreateInvoice())

    result.current.mutate({
      client_id: 5,
      line_items: [{ description: 'X', quantity: 1, unit_price_cents: 1000, tax_rate_bps: 1000 }],
      notes: null,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.invoice_number).toBe('INV-2026-001')
    expect(result.current.data?.line_items).toHaveLength(2)
  })

  it('surfaces an AppError on 422', async () => {
    server.use(
      http.post(
        '/admin/invoices',
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

    const { result } = renderHookWithProviders(() => useCreateInvoice())
    result.current.mutate({ client_id: 5, line_items: [], notes: null })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('validation-failed')
  })
})
